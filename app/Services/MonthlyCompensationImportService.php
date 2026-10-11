<?php

namespace App\Services;

use App\Models\User;
use App\Support\AdminAccess;
use App\Support\CompensationItems;
use App\Support\OfficeScope;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;
use RuntimeException;
use Throwable;
use ZipArchive;

/**
 * One workbook holds one month: a row per person with the CompensationItems
 * columns. People in the file replace their month; people absent from the
 * file are left untouched. Rows outside the importer's office, for people
 * whose type is not keyed here, or with items that do not apply to the
 * person's type are skipped and reported; rows without any amount (the
 * prefilled template lists every person) are ignored and only counted;
 * malformed rows block the import.
 */
class MonthlyCompensationImportService
{
    public const MAX_ROWS = 5000;

    public const CID_HEADER = 'เลขบัตรประชาชน';
    public const NAME_HEADER = 'ชื่อ - นามสกุล';
    public const OTHER_NAME_HEADER = 'ชื่อรายการอื่นๆ';

    public function __construct(private MonthlyCompensationService $compensations) {}

    /** @return list<string> header labels in column order */
    public static function headers(): array
    {
        $headers = [self::CID_HEADER, self::NAME_HEADER];
        foreach (CompensationItems::ITEMS as $field => $item) {
            $headers[] = $item['label'];
            if ($field === 'other_amount') {
                $headers[] = self::OTHER_NAME_HEADER;
            }
        }

        return $headers;
    }

    /** Write the import template, prefilled with the user's personnel of the keyed types. */
    public function template(?User $user): string
    {
        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet();
        $sheet->setTitle('ค่าตอบแทนรายเดือน');
        $sheet->fromArray(self::headers(), null, 'A1');
        $people = OfficeScope::apply(DB::table('person'), $user)
            ->whereIn('type_id', CompensationItems::TYPES)
            ->orderBy('office_id')->orderBy('fname')
            ->get(['cid', 'pname', 'fname', 'lname']);
        $row = 2;
        foreach ($people as $person) {
            $sheet->setCellValueExplicit('A'.$row, (string) $person->cid, DataType::TYPE_STRING);
            $sheet->setCellValue('B'.$row, trim(implode(' ', array_filter([$person->pname, $person->fname, $person->lname]))));
            $row++;
        }
        foreach (range(1, count(self::headers())) as $column) {
            $sheet->getColumnDimensionByColumn($column)->setAutoSize(true);
        }

        $path = tempnam(sys_get_temp_dir(), 'compensation-template');
        (new XlsxWriter($book))->save($path);
        $book->disconnectWorksheets();

        return $path;
    }

    /**
     * @return array{rows: list<array{number: int, cid: string, values: array<string, mixed>, existing_id: ?int, office_id: string}>,
     *               insert_count: int, update_count: int, empty_count: int, skipped: list<string>, errors: list<string>, file_hash: string}
     */
    public function preview(string $path, User $actor, int $year, int $month): array
    {
        abort_unless(AdminAccess::allows($actor, 'tax-certificates') && OfficeScope::officeFor($actor) !== '', 403);
        if ($month < 1 || $month > 12 || $year < 2500 || $year > 2700) {
            throw new RuntimeException('กรุณาเลือกเดือนและปีภาษีให้ถูกต้อง');
        }
        if (MonthlyCompensationService::yearLocked($year)) {
            throw new RuntimeException("ปี {$year} ออกหนังสือรับรองแล้ว นำเข้าไม่ได้จนกว่าสำนักงานสาธารณสุขจังหวัดจะปลดล็อก");
        }

        $result = ['rows' => [], 'insert_count' => 0, 'update_count' => 0, 'empty_count' => 0, 'skipped' => [], 'errors' => [], 'file_hash' => hash_file('sha256', $path)];
        $seen = [];
        foreach ($this->read($path) as $row) {
            $number = $row['number'];
            $cid = $row['cid'];
            if ($row['errors'] !== []) {
                array_push($result['errors'], ...array_map(fn ($error) => "แถว {$number}: {$error}", $row['errors']));

                continue;
            }
            // A person without any amount this month is not imported, so an
            // existing month is never overwritten with zeros either.
            if (array_filter(CompensationItems::fields(), fn (string $field) => (float) $row['values'][$field] != 0.0) === []) {
                $result['empty_count']++;

                continue;
            }
            if (isset($seen[$cid])) {
                $result['errors'][] = "แถว {$number}: เลขบัตรซ้ำกับแถว {$seen[$cid]}";

                continue;
            }
            $seen[$cid] = $number;

            $existing = DB::table('monthly_compensations')->where('cid', $cid)->where('income_year', $year)->where('month', $month)
                ->first(['id', 'office_id']);
            $person = DB::table('person')->where('cid', $cid)->first(['cid', 'type_id', 'office_id']);
            $skip = function (string $reason) use (&$result, $number): void {
                $result['skipped'][] = "แถว {$number}: {$reason}";
            };
            if ($person === null) {
                $skip('ไม่พบบุคลากรในทะเบียน');

                continue;
            }
            $officeId = (string) ($existing->office_id ?? $person->office_id ?? '');
            $scope = OfficeScope::officeFor($actor);
            if ($officeId === '' || ($scope !== null && $officeId !== $scope)) {
                $skip('ไม่ใช่บุคลากรของต้นสังกัดท่าน');

                continue;
            }
            if (MonthlyCompensationService::officeConfirmed($year, $officeId)) {
                $skip("ต้นสังกัด {$officeId} ยืนยันข้อมูลปี {$year} แล้ว");

                continue;
            }
            $problems = CompensationItems::problems($row['values'], $person->type_id === null ? null : (int) $person->type_id);
            if ($problems !== []) {
                $skip(implode(', ', $problems));

                continue;
            }

            $existing ? $result['update_count']++ : $result['insert_count']++;
            $result['rows'][] = [
                'number' => $number, 'cid' => $cid, 'values' => $row['values'],
                'existing_id' => $existing === null ? null : (int) $existing->id, 'office_id' => $officeId,
            ];
        }

        if ($result['rows'] === [] && $result['errors'] === [] && $result['skipped'] === []) {
            throw new RuntimeException('ไฟล์ไม่มีรายการที่มียอดเงินสำหรับนำเข้า');
        }

        return $result;
    }

    /** @return array{inserted: int, updated: int, skipped: int, empty: int} */
    public function commit(string $path, User $actor, int $year, int $month, string $expectedHash): array
    {
        abort_unless(AdminAccess::allows($actor, 'tax-certificates'), 403);
        if (! is_file($path) || ! hash_equals($expectedHash, (string) hash_file('sha256', $path))) {
            throw new RuntimeException('ไฟล์เปลี่ยนไปหลังตรวจสอบ กรุณาตรวจสอบตัวอย่างใหม่');
        }

        return DB::transaction(function () use ($path, $actor, $year, $month): array {
            DB::table('compensation_years')->where('income_year', $year)->lockForUpdate()->first();
            $preview = $this->preview($path, $actor, $year, $month);
            if ($preview['errors'] !== []) {
                throw new RuntimeException('ไฟล์มีข้อผิดพลาด กรุณาแก้ไขก่อนนำเข้า');
            }

            $offices = [];
            foreach ($preview['rows'] as $row) {
                $values = $this->compensations->rowValues($row['values']) + ['updated_by' => $actor->id, 'updated_at' => now()];
                if ($row['existing_id'] !== null) {
                    DB::table('monthly_compensations')->where('id', $row['existing_id'])->update($values);
                } else {
                    DB::table('monthly_compensations')->insert($values + [
                        'cid' => $row['cid'], 'income_year' => $year, 'month' => $month, 'office_id' => $row['office_id'],
                        'created_by' => $actor->id, 'created_at' => now(),
                    ]);
                }
                $offices[$row['office_id']] = ($offices[$row['office_id']] ?? 0) + 1;
            }
            foreach ($offices as $officeId => $count) {
                $this->compensations->audit($actor, 'imported', $year, $month, (string) $officeId, rowCount: $count);
            }

            return [
                'inserted' => $preview['insert_count'], 'updated' => $preview['update_count'],
                'skipped' => count($preview['skipped']), 'empty' => $preview['empty_count'],
            ];
        });
    }

    /** @return list<array{number: int, cid: string, values: array<string, mixed>, errors: list<string>}> */
    private function read(string $path): array
    {
        $this->checkArchive($path);
        $book = null;
        try {
            $book = (new Xlsx)->setReadDataOnly(true)->load($path);
            $sheet = $book->getSheet(0);
            $headers = self::headers();
            $actual = [];
            foreach (array_keys($headers) as $index) {
                $actual[] = trim((string) $sheet->getCell([$index + 1, 1])->getValue());
            }
            if ($actual !== $headers) {
                throw new RuntimeException('หัวคอลัมน์ไม่ตรงกับแม่แบบ กรุณาดาวน์โหลดแม่แบบล่าสุด');
            }
            if ($sheet->getHighestDataRow() - 1 > self::MAX_ROWS) {
                throw new RuntimeException('ไฟล์มีข้อมูลเกิน 5,000 แถว กรุณาแบ่งไฟล์ก่อนนำเข้า');
            }

            $rows = [];
            for ($number = 2; $number <= $sheet->getHighestDataRow(); $number++) {
                $cells = [];
                $formula = false;
                foreach (array_keys($headers) as $index) {
                    $cell = $sheet->getCell([$index + 1, $number]);
                    $formula = $formula || $cell->getDataType() === DataType::TYPE_FORMULA;
                    $value = $cell->getValue();
                    $cells[$headers[$index]] = is_string($value) ? trim($value) : $value;
                }
                $amounts = array_diff_key($cells, array_flip([self::CID_HEADER, self::NAME_HEADER, self::OTHER_NAME_HEADER]));
                if (($cells[self::CID_HEADER] ?? '') === '' && count(array_filter($amounts, fn ($v) => $v !== null && $v !== '')) === 0) {
                    continue;
                }
                $rows[] = $this->parseRow($number, $cells, $formula);
            }

            return $rows;
        } catch (RuntimeException $error) {
            throw $error;
        } catch (Throwable) {
            throw new RuntimeException('ไม่สามารถอ่านไฟล์ Excel ได้ กรุณาใช้แม่แบบ .xlsx');
        } finally {
            $book?->disconnectWorksheets();
        }
    }

    /** @param  array<string, mixed>  $cells */
    private function parseRow(int $number, array $cells, bool $formula): array
    {
        $errors = [];
        $cid = preg_replace('/\D/', '', (string) ($cells[self::CID_HEADER] ?? '')) ?? '';
        if (! preg_match('/^\d{13}$/', $cid)) {
            $errors[] = 'เลขบัตรประชาชนต้องมี 13 หลัก';
        }
        if ($formula) {
            $errors[] = 'ไม่อนุญาตให้ใช้สูตรในข้อมูล';
        }

        $values = ['other_name' => mb_substr((string) ($cells[self::OTHER_NAME_HEADER] ?? ''), 0, 255)];
        foreach (CompensationItems::ITEMS as $field => $item) {
            $raw = $cells[$item['label']] ?? null;
            $text = is_string($raw) ? str_replace([',', ' '], '', $raw) : $raw;
            if ($text === null || $text === '') {
                $values[$field] = 0;
            } elseif (is_numeric($text) && (float) $text >= 0 && (float) $text <= 9999999999.99) {
                $values[$field] = round((float) $text, 2);
            } else {
                $values[$field] = 0;
                $errors[] = "{$item['label']} ต้องเป็นตัวเลขตั้งแต่ 0 ขึ้นไป";
            }
        }

        return ['number' => $number, 'cid' => $cid, 'values' => $values, 'errors' => $errors];
    }

    private function checkArchive(string $path): void
    {
        if (! is_file($path) || filesize($path) > 5 * 1024 * 1024) {
            throw new RuntimeException('ไฟล์ต้องมีขนาดไม่เกิน 5MB');
        }
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            throw new RuntimeException('กรุณาเลือกไฟล์ Excel .xlsx');
        }
        try {
            $size = 0;
            for ($index = 0; $index < $zip->numFiles; $index++) {
                $size += $zip->statIndex($index)['size'];
            }
            if ($zip->numFiles > 1000 || $size > 20 * 1024 * 1024) {
                throw new RuntimeException('ไฟล์ Excel มีข้อมูลภายในมากเกินไป กรุณาแบ่งไฟล์');
            }
        } finally {
            $zip->close();
        }
    }
}
