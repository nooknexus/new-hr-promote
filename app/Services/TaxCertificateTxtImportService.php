<?php

namespace App\Services;

class TaxCertificateTxtImportService
{
    private const MAX_FILE_BYTES = 5 * 1024 * 1024;

    /** @var array<string, string> */
    private const INCOME_TYPE_NAMES = [
        '1' => 'ภ.ง.ด.1ก',
        '2' => 'ภ.ง.ด.1ก พิเศษ',
        '3' => 'ภ.ง.ด.2',
        '4' => 'ภ.ง.ด.3',
        '5' => 'ภ.ง.ด.2ก',
        '6' => 'ภ.ง.ด.3ก',
        '7' => 'ภ.ง.ด.53',
    ];

    /** @var array<string, string> */
    private const OUTCOME_NAMES = [
        '1' => 'กบข.',
        '2' => 'กสจ.',
    ];

    /** @var array<string, string> */
    private const TAX_TYPE_NAMES = [
        '1' => 'หัก ณ ที่จ่าย',
        '2' => 'ออกให้ตลอดไป',
        '3' => 'ออกให้ครั้งเดียว',
        '4' => 'อื่นๆ',
    ];

    /** @var array<int, string> */
    private const MONEY_FIELD_NAMES = [
        11 => 'เงินได้',
        12 => 'ภาษีหัก ณ ที่จ่าย',
        13 => 'ยอดรวมเงินได้',
        14 => 'ยอดรวมภาษี',
        17 => 'ยอดเงินกองทุน',
    ];

    /**
     * @return array{rows: array<int, array<string, int|string|null>>, errors: array<int, string>, read: int, valid: int, skipped: int}
     */
    public function preview(string $path): array
    {
        $result = [
            'rows' => [],
            'errors' => [],
            'read' => 0,
            'valid' => 0,
            'skipped' => 0,
        ];

        if (strtolower(pathinfo($path, PATHINFO_EXTENSION)) !== 'txt') {
            $result['errors'][] = 'รองรับเฉพาะไฟล์ .txt';

            return $result;
        }

        clearstatcache(true, $path);

        if (! is_file($path) || ! is_readable($path)) {
            $result['errors'][] = 'ไม่สามารถอ่านไฟล์นำเข้าได้';

            return $result;
        }

        $size = filesize($path);

        if ($size === false) {
            $result['errors'][] = 'ไม่สามารถตรวจสอบขนาดไฟล์นำเข้าได้';

            return $result;
        }

        if ($size > self::MAX_FILE_BYTES) {
            $result['errors'][] = 'ไฟล์มีขนาดเกิน 5 MB';

            return $result;
        }

        $stream = @fopen($path, 'rb');

        if ($stream === false) {
            $result['errors'][] = 'ไม่สามารถอ่านไฟล์นำเข้าได้';

            return $result;
        }

        $line = 0;

        try {
            while (($fields = fgetcsv($stream, null, '$')) !== false) {
                $line++;

                if ($this->isEmptyRow($fields)) {
                    continue;
                }

                $result['read']++;
                $fields = $this->decodeFields($fields);

                if ($fields === null) {
                    $result['skipped']++;
                    $result['errors'][] = 'บรรทัด '.$line.': รูปแบบข้อความไม่รองรับ';

                    continue;
                }

                if (count($fields) !== 20) {
                    $result['skipped']++;
                    $result['errors'][] = $this->error($line, $fields[4] ?? '', 'ต้องมีข้อมูล 20 คอลัมน์');

                    continue;
                }

                $reason = $this->validationError($fields);

                if ($reason !== null) {
                    $result['skipped']++;
                    $result['errors'][] = $this->error($line, $fields[4], $reason);

                    continue;
                }

                $result['rows'][] = $this->mapRow($fields, $line);
                $result['valid']++;
            }
        } finally {
            fclose($stream);
        }

        return $result;
    }

    /** @param array<int, string|null> $fields */
    private function isEmptyRow(array $fields): bool
    {
        foreach ($fields as $field) {
            if (trim((string) $field) !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<int, string|null> $fields
     * @return array<int, string>|null
     */
    private function decodeFields(array $fields): ?array
    {
        $decoded = [];

        foreach ($fields as $field) {
            $value = (string) $field;

            if (! mb_check_encoding($value, 'UTF-8')) {
                $value = @iconv('TIS-620', 'UTF-8', $value);

                if ($value === false) {
                    return null;
                }
            }

            $decoded[] = trim($value);
        }

        if (isset($decoded[0])) {
            $decoded[0] = preg_replace('/^\xEF\xBB\xBF/', '', $decoded[0]) ?? $decoded[0];
        }

        return $decoded;
    }

    /** @param array<int, string> $fields */
    private function validationError(array $fields): ?string
    {
        if (! preg_match('/^\d{13}$/D', $fields[4])) {
            return 'CID ต้องเป็นตัวเลข 13 หลัก';
        }

        if (! isset(self::INCOME_TYPE_NAMES[$fields[9]])) {
            return 'รหัสแบบ ภ.ง.ด. ไม่ถูกต้อง';
        }

        if (! $this->isBuddhistYear($fields[10])) {
            return 'ปีภาษี พ.ศ. ไม่ถูกต้อง';
        }

        foreach (self::MONEY_FIELD_NAMES as $index => $fieldName) {
            if ($fields[$index] !== '' && ! ctype_digit($fields[$index])) {
                return $fieldName.' ต้องเป็นจำนวนเต็มแบบ fixed-width';
            }
        }

        if (! isset(self::OUTCOME_NAMES[$fields[16]])) {
            return 'รหัสกองทุนไม่ถูกต้อง';
        }

        if (! isset(self::TAX_TYPE_NAMES[$fields[18]])) {
            return 'รหัสวิธีออกหนังสือรับรองไม่ถูกต้อง';
        }

        if (! $this->isBuddhistDate($fields[19])) {
            return 'วันที่ออกหนังสือรับรองไม่ถูกต้อง';
        }

        return null;
    }

    private function isBuddhistYear(string $year): bool
    {
        return ctype_digit($year) && (int) $year >= 2400 && (int) $year <= 3000;
    }

    private function isBuddhistDate(string $date): bool
    {
        if (! preg_match('/^\d{8}$/D', $date)) {
            return false;
        }

        $day = (int) substr($date, 0, 2);
        $month = (int) substr($date, 2, 2);
        $year = (int) substr($date, 4, 4);

        return $this->isBuddhistYear((string) $year) && checkdate($month, $day, $year - 543);
    }

    /**
     * @param array<int, string> $fields
     * @return array<string, int|string|null>
     */
    private function mapRow(array $fields, int $line): array
    {
        return [
            'line' => $line,
            'running_code1' => $fields[0],
            'department_name' => $fields[1],
            'department_tax_id' => $fields[2],
            'department_addr' => $fields[3],
            'cid' => $fields[4],
            'tax_id' => $fields[5],
            'fullname' => $fields[6],
            'addr' => $fields[7],
            'running_code2' => $fields[8],
            'income_type_id' => $fields[9],
            'income_type_name' => self::INCOME_TYPE_NAMES[$fields[9]],
            'income_year' => (int) $fields[10],
            'income_money' => $this->fixedWidthMoney($fields[11]),
            'withholding_tax' => $this->fixedWidthMoney($fields[12]),
            'total_money' => $this->fixedWidthMoney($fields[13]),
            'sumTaxAmount' => $this->fixedWidthMoney($fields[14]),
            'sumTaxText' => $fields[15] === '' ? null : $fields[15],
            'outcomeType' => $fields[16],
            'outcomeName' => self::OUTCOME_NAMES[$fields[16]],
            'outcomeAmount' => $this->fixedWidthMoney($fields[17]),
            'tax_type_id' => $fields[18],
            'tax_type_name' => self::TAX_TYPE_NAMES[$fields[18]],
            'date_report' => $fields[19],
        ];
    }

    private function fixedWidthMoney(string $value): ?string
    {
        if ($value === '' || preg_match('/^0+$/D', $value)) {
            return null;
        }

        $value = ltrim($value, '0');
        $value = str_pad($value, 3, '0', STR_PAD_LEFT);

        $whole = ltrim(substr($value, 0, -2), '0');

        return ($whole === '' ? '0' : $whole).'.'.substr($value, -2);
    }

    private function error(int $line, string $cid, string $reason): string
    {
        return 'บรรทัด '.$line.' (CID: '.$this->maskCid($cid).'): '.$reason;
    }

    private function maskCid(string $cid): string
    {
        $cid = trim($cid);

        if ($cid === '') {
            return 'ไม่ระบุ';
        }

        if (strlen($cid) <= 4) {
            return str_repeat('*', max(4, strlen($cid)));
        }

        return substr($cid, 0, 4).str_repeat('*', strlen($cid) - 4);
    }
}
