<?php

namespace Tests\Support;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class SalaryPromotionWorkbook
{
    public const HEADERS = [
        'เลขบัตรประชาชน', 'รหัส', 'รอบการประเมิน', 'ชื่อ - นามสกุล', 'เลขตำแหน่ง',
        'ตำแหน่ง', 'ระดับ', 'เลื่อน/N', 'หมายเหตุ', 'ส่วนราชการ', 'กลุ่มงาน',
        'เงินเดือนก่อนเลื่อน', 'ฐานในการคำนวณ', 'ร้อยละที่ได้เลื่อน',
        'เงินที่ได้เลื่อน', 'เงินค่าตอบแทนพิเศษ', 'เงินเดือนที่ได้รับ',
    ];

    public const ROW = [
        '3650200600388', '36502', '2022-10-01', 'นายสิทธิชัย ป้องม่วง',
        '01พล00139', 'ผู้ช่วยพยาบาล', '', 'Y', '', 'รพร.นครไทย', '',
        '13,780', '13,780', '4.4', '610', '', '14,390',
    ];

    public string $path;

    /** @param array<int, array<int, mixed>> $rows */
    public function __construct(array $rows = [self::ROW], ?callable $configure = null)
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([self::HEADERS, ...$rows]);
        $configure?->__invoke($sheet);

        $this->path = sys_get_temp_dir().'/promotion-import-'.bin2hex(random_bytes(8)).'.xlsx';
        (new Xlsx($spreadsheet))->save($this->path);
        $spreadsheet->disconnectWorksheets();
    }

    public function __destruct()
    {
        if (is_file($this->path)) {
            unlink($this->path);
        }
    }
}
