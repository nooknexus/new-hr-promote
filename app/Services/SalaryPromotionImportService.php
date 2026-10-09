<?php

namespace App\Services;

use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Throwable;

class SalaryPromotionImportService
{
    private const MAX_DATA_ROWS = 5000;

    private const HEADERS = [
        'เลขบัตรประชาชน' => 'cid',
        'รหัส' => 'code',
        'รอบการประเมิน' => 'evaluation_date',
        'ชื่อ - นามสกุล' => 'full_name',
        'เลขตำแหน่ง' => 'no_position',
        'ตำแหน่ง' => 'position',
        'ระดับ' => 'level',
        'เลื่อน/N' => 'promote',
        'หมายเหตุ' => 'ps',
        'ส่วนราชการ' => 'office',
        'กลุ่มงาน' => 'department',
        'เงินเดือนก่อนเลื่อน' => 'before_salary',
        'ฐานในการคำนวณ' => 'base',
        'ร้อยละที่ได้เลื่อน' => 'percent',
        'เงินที่ได้เลื่อน' => 'sum',
        'เงินค่าตอบแทนพิเศษ' => 'extra',
        'เงินเดือนที่ได้รับ' => 'new_salary',
    ];

    private const REQUIRED = [
        'cid', 'evaluation_date', 'no_position', 'promote',
        'before_salary', 'base', 'percent', 'sum', 'new_salary',
    ];

    private const MONEY = ['before_salary', 'base', 'percent', 'sum', 'extra', 'new_salary'];

    /** Workbook fields copied onto the matching person row; blank cells keep the stored value. */
    private const PERSON_FIELDS = ['no_position' => 'no_position', 'position' => 'position', 'level' => 'position_level'];

    /**
     * @param  string|null  $officeScope  When set, rows for personnel outside this office are skipped; null keeps every row.
     * @param  list<int>|null  $typeScope  When set, rows for personnel outside these types are skipped; null keeps every row.
     * @return array{rows: array<int, array<string, mixed>>, insert_count: int, update_count: int, unmatched_person_count: int, unmatched_user_count: int, skipped_count: int, warnings: array<int, string>, errors: array<int, string>, baseline_hash: string}
     */
    public function preview(string $path, ?string $officeScope = null, ?array $typeScope = null): array
    {
        if (! is_file($path) || filesize($path) > 10 * 1024 * 1024) {
            throw ValidationException::withMessages(['file' => 'ไฟล์ไม่ถูกต้องหรือมีขนาดเกิน 10 MB']);
        }

        try {
            if (IOFactory::identify($path) !== 'Xlsx') {
                throw ValidationException::withMessages(['file' => 'รองรับเฉพาะไฟล์ .xlsx']);
            }
            $spreadsheet = IOFactory::load($path);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw ValidationException::withMessages(['file' => 'ไม่สามารถอ่านไฟล์ Excel ได้']);
        }

        try {
            $sheet = $spreadsheet->getActiveSheet();
            $headers = $this->headerMap($sheet);
            $lastRow = $sheet->getHighestDataRow();
            if ($lastRow - 1 > self::MAX_DATA_ROWS) {
                throw ValidationException::withMessages(['file' => 'ไฟล์มีข้อมูลเกิน 5,000 แถว']);
            }

            $result = [
                'rows' => [],
                'insert_count' => 0,
                'update_count' => 0,
                'unmatched_person_count' => 0,
                'unmatched_user_count' => 0,
                'skipped_count' => 0,
                'warnings' => [],
                'errors' => [],
            ];
            $seen = [];
            $baselines = [];

            for ($number = 2; $number <= $lastRow; $number++) {
                [$row, $errors] = $this->readRow($sheet, $number, $headers);
                if ($row === null) {
                    continue;
                }

                foreach ($errors as $error) {
                    $result['errors'][] = "แถว {$number}: {$error}";
                }
                if ($errors !== []) {
                    continue;
                }

                if (! $this->withinScope($row['cid'], $officeScope, $typeScope)) {
                    $result['skipped_count']++;
                    continue;
                }

                $key = $row['cid'].'|'.$row['evaluation_date'];
                if (isset($seen[$key])) {
                    $result['errors'][] = "แถว {$number}: เลขบัตรประชาชนและรอบการประเมินซ้ำในไฟล์";
                    continue;
                }
                $seen[$key] = true;

                $existing = DB::table('data_promote')->where('CID', $row['cid'])
                    ->where('evaluation_date', $row['evaluation_date'])
                    ->first();
                if ($existing?->deleted_at !== null) {
                    $result['errors'][] = "แถว {$number}: รายการเดิมถูกยกเลิกแล้ว";
                    continue;
                }
                $existing ? $result['update_count']++ : $result['insert_count']++;

                $person = null;
                $user = null;
                if ($this->personUpdates($row) !== []) {
                    $person = DB::table('person')->where('cid', $row['cid'])->first(['cid', ...array_values(self::PERSON_FIELDS)]);
                    if ($person === null) {
                        $result['unmatched_person_count']++;
                    }
                }
                if ($row['no_position'] !== null) {
                    $users = DB::table('users')->where('pid', $row['cid'])
                        ->orWhere('thaid_pid', $row['cid'])->get(['id', 'no_position']);
                    if ($users->count() > 1) {
                        $result['errors'][] = "แถว {$number}: พบผู้ใช้หลายบัญชีที่ตรงกับเลขบัตรเดียวกัน";
                        continue;
                    }
                    $user = $users->first();
                    if ($user === null) {
                        $result['unmatched_user_count']++;
                    }
                }
                $baselines[$key] = $this->rowBaseline($existing, $person, $user);

                $expected = (float) $row['before_salary'] + ((float) $row['base'] * (float) $row['percent'] / 100);
                if (abs($expected - (float) $row['new_salary']) > 0.01
                    || abs(((float) $row['new_salary'] - (float) $row['before_salary']) - (float) $row['sum']) > 0.01) {
                    $result['warnings'][] = "แถว {$number}: ตัวเลขเงินเดือนในไฟล์ไม่ตรงสูตร ระบบจะยึดค่าจากไฟล์";
                }

                $result['rows'][] = $row;
            }
            $result['baseline_hash'] = $this->baselineHash($baselines);

            return $result;
        } finally {
            $spreadsheet->disconnectWorksheets();
        }
    }

    /** @return array{insert_count: int, update_count: int, unmatched_person_count: int, unmatched_user_count: int, skipped_count: int} */
    public function commit(string $path, int $actorId, string $expectedBaselineHash, ?string $officeScope = null, ?array $typeScope = null): array
    {
        return DB::transaction(function () use ($path, $actorId, $expectedBaselineHash, $officeScope, $typeScope): array {
            $preview = $this->preview($path, $officeScope, $typeScope);
            if ($preview['errors'] !== []) {
                throw ValidationException::withMessages([
                    'file' => implode(' | ', array_slice($preview['errors'], 0, 10)),
                ]);
            }
            if (! hash_equals($expectedBaselineHash, $preview['baseline_hash'])) {
                throw ValidationException::withMessages(['file' => 'ข้อมูลเปลี่ยนหลังแสดงตัวอย่าง กรุณาตรวจสอบอีกครั้ง']);
            }

            $result = [
                'insert_count' => 0,
                'update_count' => 0,
                'unmatched_person_count' => 0,
                'unmatched_user_count' => 0,
                'skipped_count' => $preview['skipped_count'],
            ];
            $lockedBaselines = [];
            // First-seen hashes per CID, so a later row for the same person compares
            // against the state the preview saw rather than this import's own writes.
            $personBaselines = [];
            $userBaselines = [];

            foreach ($preview['rows'] as $row) {
                $person = null;
                $user = null;
                $personUpdates = $this->personUpdates($row);
                if ($personUpdates !== []) {
                    $person = DB::table('person')->where('cid', $row['cid'])
                        ->lockForUpdate()->first(['cid', ...array_values(self::PERSON_FIELDS)]);
                    if ($person === null) {
                        $result['unmatched_person_count']++;
                    }
                    $personBaselines[$row['cid']] ??= $this->recordHash($person);
                }
                if ($row['no_position'] !== null) {
                    $users = DB::table('users')->where('pid', $row['cid'])
                        ->orWhere('thaid_pid', $row['cid'])
                        ->lockForUpdate()->get(['id', 'no_position']);
                    if ($users->count() > 1) {
                        throw ValidationException::withMessages(['file' => 'พบผู้ใช้หลายบัญชีที่ตรงกับเลขบัตรเดียวกัน']);
                    }
                    $user = $users->first();
                    if ($user === null) {
                        $result['unmatched_user_count']++;
                    }
                    $userBaselines[$row['cid']] ??= $this->recordHash($user);
                }

                $promotionQuery = DB::table('data_promote')
                    ->where('CID', $row['cid'])
                    ->where('evaluation_date', $row['evaluation_date']);
                $existing = (clone $promotionQuery)->lockForUpdate()->first();
                if ($existing?->deleted_at !== null) {
                    throw ValidationException::withMessages(['file' => 'รายการเดิมถูกยกเลิกหลังแสดงตัวอย่าง']);
                }
                $lockedBaseline = $this->rowBaseline($existing, $person, $user);
                if ($personUpdates !== []) {
                    $lockedBaseline['person'] = $personBaselines[$row['cid']];
                }
                if ($row['no_position'] !== null) {
                    $lockedBaseline['user'] = $userBaselines[$row['cid']];
                }
                $lockedBaselines[$row['cid'].'|'.$row['evaluation_date']] = $lockedBaseline;

                $values = [
                    'CID' => $row['cid'],
                    'evaluation_date' => $row['evaluation_date'],
                    'promote' => $row['promote'],
                    'ps' => $row['ps'],
                    'office' => $row['office'],
                    'department' => $row['department'],
                    'before_salary' => $row['before_salary'],
                    'base' => $row['base'],
                    'percent' => $row['percent'],
                    'sum' => $row['sum'],
                    'extra' => $row['extra'],
                    'new_salary' => $row['new_salary'],
                    'update_date' => now(),
                ];
                $before = [
                    'promotion' => $existing === null ? null : array_intersect_key((array) $existing, $values),
                    'person_no_position' => $person?->no_position,
                    'person_position' => $person?->position,
                    'person_position_level' => $person?->position_level,
                    'user_no_position' => $user?->no_position,
                ];

                if ($existing) {
                    $promotionQuery->update($values);
                    $promotionId = $existing->id;
                    $action = 'imported_updated';
                    $result['update_count']++;
                } else {
                    $promotionId = DB::table('data_promote')->insertGetId($values);
                    $action = 'imported_created';
                    $result['insert_count']++;
                }

                if ($person) {
                    DB::table('person')->where('cid', $row['cid'])->update($personUpdates);
                }
                if ($user) {
                    DB::table('users')->where('id', $user->id)->update(['no_position' => $row['no_position']]);
                }
                $after = [
                    'promotion' => array_diff_key($values, ['CID' => true]),
                    'person_no_position' => $person ? ($personUpdates['no_position'] ?? $person->no_position) : null,
                    'person_position' => $person ? ($personUpdates['position'] ?? $person->position) : null,
                    'person_position_level' => $person ? ($personUpdates['position_level'] ?? $person->position_level) : null,
                    'user_no_position' => $user ? $row['no_position'] : null,
                ];

                DB::table('data_promote_audits')->insert([
                    'promotion_id' => $promotionId,
                    'actor_id' => $actorId,
                    'action' => $action,
                    'before_data' => json_encode($before, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                    'after_data' => json_encode($after, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                    'created_at' => now(),
                ]);
            }
            if (! hash_equals($expectedBaselineHash, $this->baselineHash($lockedBaselines))) {
                throw ValidationException::withMessages(['file' => 'ข้อมูลเปลี่ยนระหว่างยืนยัน กรุณาตรวจสอบอีกครั้ง']);
            }

            return $result;
        });
    }

    /** @return array<string, string> person columns to update from this row's non-blank cells */
    /** Whether the row's person is inside the importer's office and personnel type scope. */
    private function withinScope(string $cid, ?string $officeScope, ?array $typeScope): bool
    {
        if ($officeScope === null && $typeScope === null) {
            return true;
        }
        if ($officeScope === '' || $typeScope === []) {
            return false;
        }

        $person = DB::table('person')->where('cid', $cid)->first(['office_id', 'type_id']);

        return $person !== null
            && ($officeScope === null || (string) $person->office_id === $officeScope)
            && ($typeScope === null || in_array((int) $person->type_id, $typeScope, true));
    }

    private function personUpdates(array $row): array
    {
        $updates = [];
        foreach (self::PERSON_FIELDS as $field => $column) {
            if (($row[$field] ?? null) !== null) {
                $updates[$column] = $row[$field];
            }
        }

        return $updates;
    }

    private function rowBaseline(?object $promotion, ?object $person, ?object $user): array
    {
        return [
            'promotion' => $this->recordHash($promotion),
            'person' => $this->recordHash($person),
            'user' => $this->recordHash($user),
        ];
    }

    private function recordHash(?object $record): ?string
    {
        return $record === null ? null : hash('sha256', json_encode($record, JSON_THROW_ON_ERROR));
    }

    private function baselineHash(array $baselines): string
    {
        return hash('sha256', json_encode($baselines, JSON_THROW_ON_ERROR));
    }

    /** @return array<string, int> */
    private function headerMap(Worksheet $sheet): array
    {
        $headers = [];
        $lastColumn = Coordinate::columnIndexFromString($sheet->getHighestDataColumn());
        for ($column = 1; $column <= $lastColumn; $column++) {
            $label = $this->normalizeText((string) $sheet->getCell(Coordinate::stringFromColumnIndex($column).'1')->getValue());
            if (! isset(self::HEADERS[$label])) {
                continue;
            }
            $field = self::HEADERS[$label];
            if (isset($headers[$field])) {
                throw ValidationException::withMessages(['file' => "หัวตาราง {$label} ซ้ำ"]);
            }
            $headers[$field] = $column;
        }
        $missing = array_diff(self::REQUIRED, array_keys($headers));
        if ($missing !== []) {
            throw ValidationException::withMessages(['file' => 'หัวตารางที่จำเป็นไม่ครบ']);
        }

        return $headers;
    }

    /** @param array<string, int> $headers
     *  @return array{0: ?array<string, mixed>, 1: array<int, string>}
     */
    private function readRow(Worksheet $sheet, int $number, array $headers): array
    {
        $values = [];
        $errors = [];
        foreach ($headers as $field => $column) {
            $cell = $sheet->getCell(Coordinate::stringFromColumnIndex($column).$number);
            $raw = $cell->getValue();
            if ($cell->getDataType() === DataType::TYPE_FORMULA || $cell->getDataType() === DataType::TYPE_ERROR) {
                $errors[] = "เซลล์ {$field} เป็นสูตรหรือข้อผิดพลาด";
                $values[$field] = null;
                continue;
            }
            $values[$field] = $field === 'evaluation_date'
                ? $this->parseDate($cell)
                : ($field === 'no_position' && is_numeric($raw)
                    ? $this->normalizeText((string) $cell->getFormattedValue())
                    : $this->normalizeText((string) ($raw ?? '')));
        }
        if (array_filter($values, fn ($value) => $value !== null && $value !== '') === []) {
            return [null, []];
        }

        $row = [
            'cid' => $values['cid'] ?? '',
            'evaluation_date' => $values['evaluation_date'] ?? null,
            'no_position' => ($values['no_position'] ?? '') === '' ? null : $values['no_position'],
            'position' => ($values['position'] ?? '') === '' ? null : $values['position'],
            'level' => ($values['level'] ?? '') === '' ? null : $values['level'],
            'promote' => strtoupper($values['promote'] ?? ''),
            'ps' => $values['ps'] ?? null,
            'office' => $values['office'] ?? null,
            'department' => $values['department'] ?? null,
            'full_name' => $values['full_name'] ?? null,
        ];
        if (! preg_match('/^\d{13}$/', $row['cid'])) {
            $errors[] = 'เลขบัตรประชาชนต้องมี 13 หลัก';
        }
        if ($row['evaluation_date'] === null) {
            $errors[] = 'รอบการประเมินไม่ถูกต้อง';
        }
        if (! in_array($row['promote'], ['Y', 'N'], true)) {
            $errors[] = 'ผลเลื่อนขั้นต้องเป็น Y หรือ N';
        }
        if ($row['no_position'] !== null && mb_strlen($row['no_position']) > 255) {
            $errors[] = 'เลขตำแหน่งยาวเกิน 255 ตัวอักษร';
        }
        foreach (['ps', 'office', 'department', 'position', 'level'] as $field) {
            if (mb_strlen((string) $row[$field]) > 255) {
                $errors[] = "{$field} ยาวเกิน 255 ตัวอักษร";
            }
        }
        foreach (self::MONEY as $field) {
            $value = $values[$field] ?? '';
            if ($value === '' && $field === 'extra') {
                $row[$field] = null;
                continue;
            }
            $numberText = str_replace(',', '', (string) $value);
            $max = $field === 'percent' ? 100 : 9999999.99;
            if (! is_numeric($numberText) || (float) $numberText < 0 || (float) $numberText > $max) {
                $errors[] = "{$field} ต้องเป็นตัวเลขที่ไม่ติดลบ";
                $row[$field] = null;
                continue;
            }
            $row[$field] = number_format((float) $numberText, 2, '.', '');
        }
        if ($row['before_salary'] !== null && $row['new_salary'] !== null
            && (float) $row['new_salary'] < (float) $row['before_salary']) {
            $errors[] = 'เงินเดือนใหม่ต้องไม่น้อยกว่าเงินเดือนเดิม';
        }

        return [$row, $errors];
    }

    private function parseDate(Cell $cell): ?string
    {
        $value = $cell->getValue();
        if (is_numeric($value) && ExcelDate::isDateTime($cell)) {
            return ExcelDate::excelToDateTimeObject((float) $value)->format('Y-m-d');
        }
        if (! is_string($value)) {
            return null;
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', trim($value));

        return $date && $date->format('Y-m-d') === trim($value) ? $date->format('Y-m-d') : null;
    }

    private function normalizeText(string $value): string
    {
        return trim(preg_replace('/[\s\p{Z}]+/u', ' ', $value) ?? $value);
    }
}
