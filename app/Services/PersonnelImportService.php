<?php

namespace App\Services;

use App\Models\User;
use App\Support\OfficeScope;
use App\Support\PersonnelTypeScope;
use App\Support\UserPositionNumber;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;
use RuntimeException;

class PersonnelImportService
{
    private const MAX_DATA_ROWS = 5000;

    /**
     * Fixed template columns. Row 1 is a header row and is skipped; header text is
     * not checked because users often rename it.
     *
     * @var array<string, string>
     */
    private const COLUMNS = [
        'cid' => 'A',
        'pname' => 'B',
        'fname' => 'C',
        'lname' => 'D',
        'type_id' => 'E',
        'type_name' => 'F',
        'office_id' => 'G',
        'office_name' => 'H',
        'employment_started_at' => 'I',
        'position' => 'J',
        'position_level' => 'K',
        'no_position' => 'L',
    ];

    /**
     * The import template with a 'รหัสอ้างอิง' sheet listing the personnel type
     * codes and the active office codes the user may import into. The office
     * list is read from the offices registry on every download so it never
     * goes stale.
     */
    public function template(?User $user): string
    {
        $book = IOFactory::load(resource_path('templates/personnel-import-template.xlsx'));
        $data = $book->getSheet(0);
        $headerStyle = $data->getStyle('A1')->exportArray();
        $bodyStyle = $data->getStyle('H2')->exportArray();

        $codes = new Worksheet($book, 'รหัสอ้างอิง');
        $book->addSheet($codes, 1);
        $codes->fromArray(['รหัสประเภท', 'ประเภทเจ้าหน้าที่', 'รหัสหน่วยงาน', 'หน่วยงาน'], null, 'A1');

        $row = 2;
        foreach (PersonnelTypeScope::ALL_TYPES as $id => $name) {
            $codes->setCellValue('A'.$row, $id);
            $codes->setCellValue('B'.$row, $name);
            $row++;
        }
        $lastRow = $row - 1;

        $row = 2;
        foreach (OfficeScope::options($user) as $office) {
            $codes->setCellValueExplicit('C'.$row, $office->office_id, DataType::TYPE_STRING);
            $codes->setCellValue('D'.$row, $office->office_name);
            $row++;
        }
        $lastRow = max($lastRow, $row - 1);

        $codes->getStyle('A1:D1')->applyFromArray($headerStyle);
        $codes->getRowDimension(1)->setRowHeight(32);
        if ($lastRow >= 2) {
            $codes->getStyle('A2:D'.$lastRow)->applyFromArray($bodyStyle);
            $codes->getStyle('A2:A'.$lastRow)->getAlignment()->setHorizontal('center');
            $codes->getStyle('C2:C'.$lastRow)->getAlignment()->setHorizontal('center');
            $codes->getStyle('C2:C'.$lastRow)->getNumberFormat()->setFormatCode('@');
        }
        foreach (['A' => 14, 'B' => 22, 'C' => 14, 'D' => 40] as $column => $width) {
            $codes->getColumnDimension($column)->setWidth($width);
        }
        $codes->freezePane('A2');

        $guide = $book->getSheetByName('คำแนะนำ');
        if ($guide !== null) {
            $line = $guide->getHighestDataRow() + 1;
            $guide->setCellValue('A'.$line, ($line - 2).'. รหัสประเภท (คอลัมน์ E) และรหัสหน่วยงาน (คอลัมน์ G) ดูได้ในชีต ‘รหัสอ้างอิง’ รหัสหน่วยงานเป็นตัวเลข 2 หลัก เช่น 05');
            $guide->duplicateStyle($guide->getStyle('A'.($line - 1)), 'A'.$line);
        }

        $book->setActiveSheetIndex(0);
        $path = tempnam(sys_get_temp_dir(), 'personnel-template');
        (new XlsxWriter($book))->save($path);
        $book->disconnectWorksheets();

        return $path;
    }

    /**
     * @param  string|null  $officeScope  When set, every row must belong to this office; null allows every office.
     * @return array{created: int, updated: int, skipped: int}
     */
    public function import(string $path, ?string $officeScope = null): array
    {
        if ($officeScope === '') {
            throw new RuntimeException('บัญชีนี้ยังไม่ได้กำหนดหน่วยงาน จึงไม่สามารถนำเข้าข้อมูลบุคลากรได้');
        }

        $spreadsheet = IOFactory::load($path);
        $sheet = $spreadsheet->getActiveSheet();
        $lastRow = $sheet->getHighestDataRow();

        if ($lastRow - 1 > self::MAX_DATA_ROWS) {
            throw new RuntimeException('ไฟล์มีข้อมูลเกิน '.self::MAX_DATA_ROWS.' รายการ กรุณาแบ่งไฟล์ก่อนนำเข้า');
        }

        $records = [];
        $skipped = 0;
        $errors = [];

        for ($row = 2; $row <= $lastRow; $row++) {
            $record = $this->recordFromRow($sheet, $row);

            if ($this->isEmptyRecord($record)) {
                $skipped++;

                continue;
            }

            $validator = Validator::make($record, [
                'cid' => ['required', 'digits:13'],
                'pname' => ['nullable', 'string', 'max:255'],
                'fname' => ['required', 'string', 'max:255'],
                'lname' => ['required', 'string', 'max:255'],
                'type_id' => ['nullable', 'integer', 'between:0,255'],
                'type_name' => ['nullable', 'string', 'max:255'],
                'office_id' => ['nullable', 'string', 'max:2'],
                'office_name' => ['nullable', 'string', 'max:255'],
                'employment_started_at' => ['nullable', 'date', 'before_or_equal:today'],
                'position' => ['nullable', 'string', 'max:255'],
                'position_level' => ['nullable', 'string', 'max:255'],
                'no_position' => ['nullable', 'string', 'max:255'],
            ], [
                'cid.required' => 'ต้องระบุเลขประจำตัวประชาชน',
                'cid.digits' => 'เลขประจำตัวประชาชนต้องมี 13 หลัก',
                'fname.required' => 'ต้องระบุชื่อ',
                'lname.required' => 'ต้องระบุนามสกุล',
                'employment_started_at.date' => 'วันเริ่มงานไม่ถูกต้อง',
                'employment_started_at.before_or_equal' => 'วันเริ่มงานต้องไม่เกินวันนี้',
                'position.max' => 'ตำแหน่งต้องไม่เกิน 255 ตัวอักษร',
                'position_level.max' => 'ระดับต้องไม่เกิน 255 ตัวอักษร',
                'no_position.max' => 'เลขที่ตำแหน่งต้องไม่เกิน 255 ตัวอักษร',
            ]);

            if ($validator->fails()) {
                $errors[] = 'แถว '.$row.': '.implode(' ', $validator->errors()->all());

                continue;
            }

            $record = $validator->validated();

            $existing = DB::table('person')->where('cid', $record['cid'])->first(['office_id']);

            if ($officeScope !== null) {
                $record['office_id'] ??= $officeScope;

                if ($record['office_id'] !== $officeScope || ($existing !== null && $existing->office_id !== $officeScope)) {
                    $errors[] = 'แถว '.$row.': นำเข้าได้เฉพาะบุคลากรของหน่วยงานตนเอง';

                    continue;
                }
            }

            // Office codes come from the offices registry: a new assignment must
            // be an active office, keeping a person's current office is allowed,
            // and the office name is always taken from the registry (column H is
            // informational only).
            if ($record['office_id'] !== null) {
                $keepsCurrentOffice = $existing !== null && (string) $existing->office_id === (string) $record['office_id'];

                if (! OfficeScope::exists($record['office_id'], activeOnly: ! $keepsCurrentOffice)) {
                    $errors[] = 'แถว '.$row.': ไม่พบรหัสต้นสังกัด '.$record['office_id'].' ในทะเบียนต้นสังกัด หรือต้นสังกัดถูกปิดใช้งาน';

                    continue;
                }

                $record['office_name'] = OfficeScope::nameFor($record['office_id']);
            } else {
                $record['office_name'] = null;
            }

            $records[] = $record;
        }

        if ($errors !== []) {
            throw new RuntimeException('ไม่สามารถนำเข้าไฟล์ได้ กรุณาแก้ไขข้อมูลก่อน: '.implode(' | ', array_slice($errors, 0, 10)));
        }

        $created = 0;
        $updated = 0;

        DB::transaction(function () use ($records, &$created, &$updated): void {
            foreach ($records as $record) {
                $existing = DB::table('person')->where('cid', $record['cid'])->first(['no_position']);

                // A blank position number cell keeps the current number, so only a
                // filled cell can change the linked account.
                if ($record['no_position'] !== null) {
                    UserPositionNumber::syncFromPerson($record['cid'], $existing?->no_position, $record['no_position']);
                }

                if ($existing !== null) {
                    // Blank optional cells do not erase profile data. Administrators can clear
                    // a value deliberately from the personnel form when necessary.
                    $update = array_filter($record, fn ($value) => $value !== null);
                    unset($update['cid']);
                    DB::table('person')->where('cid', $record['cid'])->update($update);
                    $updated++;

                    continue;
                }

                DB::table('person')->insert($record);
                $created++;
            }
        });

        return compact('created', 'updated', 'skipped');
    }

    private function recordFromRow(Worksheet $sheet, int $row): array
    {
        $record = [
            'cid' => null,
            'pname' => null,
            'fname' => null,
            'lname' => null,
            'type_id' => null,
            'type_name' => null,
            'office_id' => null,
            'office_name' => null,
            'employment_started_at' => null,
            'position' => null,
            'position_level' => null,
            'no_position' => null,
        ];

        foreach (self::COLUMNS as $field => $column) {
            $cell = $sheet->getCell($column.$row);
            $value = $cell->getValue();

            if ($field === 'employment_started_at') {
                $record[$field] = $this->parseDate($value, $cell);

                continue;
            }

            $value = is_string($value) ? trim($value) : $value;
            $record[$field] = $value === '' ? null : $value;
        }

        $record['cid'] = $record['cid'] === null ? null : preg_replace('/\s+/', '', (string) $record['cid']);
        $record['type_id'] = $record['type_id'] === null ? null : (int) $record['type_id'];
        // Excel stores typed numbers such as position number 1001 as numbers.
        foreach (['position', 'position_level', 'no_position'] as $field) {
            if (is_float($record[$field]) && floor($record[$field]) === $record[$field]) {
                $record[$field] = (string) (int) $record[$field];
            } elseif (is_int($record[$field]) || is_float($record[$field])) {
                $record[$field] = (string) $record[$field];
            }
        }
        // Excel turns typed office codes such as 05 into the number 5.
        if (is_int($record['office_id']) || is_float($record['office_id']) || (is_string($record['office_id']) && ctype_digit($record['office_id']))) {
            $record['office_id'] = str_pad((string) (int) $record['office_id'], 2, '0', STR_PAD_LEFT);
        }

        return $record;
    }

    private function parseDate(mixed $value, object $cell): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value) && ExcelDate::isDateTime($cell)) {
            return ExcelDate::excelToDateTimeObject((float) $value)->format('Y-m-d');
        }

        $value = trim((string) $value);

        foreach (['Y-m-d', 'd/m/Y', 'd/m/y'] as $format) {
            $date = DateTimeImmutable::createFromFormat('!'.$format, $value);

            if ($date !== false && $date->format($format) === $value) {
                $year = (int) $date->format('Y');

                return ($year >= 2400 ? $date->modify('-543 years') : $date)->format('Y-m-d');
            }
        }

        return $value;
    }

    /** @param array<string, mixed> $record */
    private function isEmptyRecord(array $record): bool
    {
        return collect($record)->filter(fn ($value) => $value !== null && $value !== '')->isEmpty();
    }
}
