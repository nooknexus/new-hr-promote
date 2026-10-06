<?php

namespace App\Services;

use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use RuntimeException;

class PersonnelImportService
{
    private const MAX_DATA_ROWS = 5000;

    /** @var array<string, string> */
    private const HEADERS = [
        'เลขประจำตัวประชาชน' => 'cid',
        'คำนำหน้า' => 'pname',
        'ชื่อ' => 'fname',
        'นามสกุล' => 'lname',
        'รหัสประเภท' => 'type_id',
        'ประเภทเจ้าหน้าที่' => 'type_name',
        'รหัสหน่วยงาน' => 'office_id',
        'หน่วยงาน' => 'office_name',
        'วันเริ่มงาน' => 'employment_started_at',
    ];

    /**
     * @return array{created: int, updated: int, skipped: int}
     */
    public function import(string $path): array
    {
        $spreadsheet = IOFactory::load($path);
        $sheet = $spreadsheet->getActiveSheet();
        $headerMap = $this->headerMap($sheet);
        $lastRow = $sheet->getHighestDataRow();

        if ($lastRow - 1 > self::MAX_DATA_ROWS) {
            throw new RuntimeException('ไฟล์มีข้อมูลเกิน '.self::MAX_DATA_ROWS.' รายการ กรุณาแบ่งไฟล์ก่อนนำเข้า');
        }

        $records = [];
        $skipped = 0;
        $errors = [];

        for ($row = 2; $row <= $lastRow; $row++) {
            $record = $this->recordFromRow($sheet, $row, $headerMap);

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
            ], [
                'cid.required' => 'ต้องระบุเลขประจำตัวประชาชน',
                'cid.digits' => 'เลขประจำตัวประชาชนต้องมี 13 หลัก',
                'fname.required' => 'ต้องระบุชื่อ',
                'lname.required' => 'ต้องระบุนามสกุล',
                'employment_started_at.date' => 'วันเริ่มงานไม่ถูกต้อง',
                'employment_started_at.before_or_equal' => 'วันเริ่มงานต้องไม่เกินวันนี้',
            ]);

            if ($validator->fails()) {
                $errors[] = 'แถว '.$row.': '.implode(' ', $validator->errors()->all());

                continue;
            }

            $records[] = $validator->validated();
        }

        if ($errors !== []) {
            throw new RuntimeException('ไม่สามารถนำเข้าไฟล์ได้ กรุณาแก้ไขข้อมูลก่อน: '.implode(' | ', array_slice($errors, 0, 10)));
        }

        $created = 0;
        $updated = 0;

        DB::transaction(function () use ($records, &$created, &$updated): void {
            foreach ($records as $record) {
                $exists = DB::table('person')->where('cid', $record['cid'])->exists();

                if ($exists) {
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

    /** @return array<string, int> */
    private function headerMap(Worksheet $sheet): array
    {
        $headers = [];
        $lastColumn = Coordinate::columnIndexFromString($sheet->getHighestDataColumn());

        for ($column = 1; $column <= $lastColumn; $column++) {
            $label = $this->normaliseHeader((string) $sheet->getCell(Coordinate::stringFromColumnIndex($column).'1')->getValue());

            if (isset(self::HEADERS[$label])) {
                $headers[self::HEADERS[$label]] = $column;
            }
        }

        $missing = array_values(array_diff(['cid', 'fname', 'lname', 'employment_started_at'], array_keys($headers)));

        if ($missing !== []) {
            throw new RuntimeException('หัวตารางไม่ครบ กรุณาดาวน์โหลด template ล่าสุดจากหน้านี้แล้วกรอกข้อมูลใหม่');
        }

        return $headers;
    }

    /** @param array<string, int> $headerMap */
    private function recordFromRow(Worksheet $sheet, int $row, array $headerMap): array
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
        ];

        foreach ($headerMap as $field => $column) {
            $cell = $sheet->getCell(Coordinate::stringFromColumnIndex($column).$row);
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

    private function normaliseHeader(string $value): string
    {
        return trim(str_replace("\u{00A0}", ' ', $value));
    }
}
