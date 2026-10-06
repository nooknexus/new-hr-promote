<?php

use App\Services\LeaveSheetSyncException;
use App\Services\LeaveSheetSyncService;

test('it converts a Google Sheets edit link into a CSV export for the selected tab', function () {
    $url = (new LeaveSheetSyncService)->normalizeSheetUrl(
        'https://docs.google.com/spreadsheets/d/sheet_id-123/edit?usp=sharing#gid=456'
    );

    expect($url)->toBe('https://docs.google.com/spreadsheets/d/sheet_id-123/export?format=csv&gid=456');
});

test('it rejects a sheet URL that could target a non-Google host', function () {
    (new LeaveSheetSyncService)->normalizeSheetUrl('https://example.com/spreadsheets/d/sheet_id/edit');
})->throws(LeaveSheetSyncException::class);

test('it imports CSV data when a header is repeated by using the first column', function () {
    $csv = implode("\n", [
        'เลขประจำตัวประชาชน,ชื่อ + นามสกุล,ประเภทการลา,วันที่(เริ่ม),วันที่(สิ้นสุด),จำนวนวัน,สถานะวันลา,หมายเหตุ,หมายเหตุ',
        '1234567890123,ทดสอบ บุคลากร,ลาป่วย,01/10/2568,01/10/2568,1,อนุมัติ,หมายเหตุแรก,หมายเหตุซ้ำ',
    ]);
    $service = new LeaveSheetSyncService;
    $parser = \Closure::bind(fn (string $contents) => $this->parseCsv($contents), $service, $service);

    $records = $parser($csv);

    expect($records)->toHaveCount(1)
        ->and($records[0]['หมายเหตุ'])->toBe('หมายเหตุแรก');
});

test('it imports a leave-sheet row without an optional remarks column', function () {
    $csv = implode("\n", [
        ',เลขประจำตัวประชาชน,คำนำหน้า,ชื่อ + นามสกุล,ตำแหน่งสายงาน,ประเภทเจ้าหน้าที่,ประเภทการลา,วันที่(เริ่ม),วันที่(สิ้นสุด),จำนวนวัน,สถานะวันลา',
        '1631,1640100101537,นางสาว,ไพลิน เปรมสุโข,,ข้าราชการ,ลาพักผ่อน,30/9/26,30/9/26,1,อนุมัติ',
    ]);
    $service = new LeaveSheetSyncService;
    $parser = \Closure::bind(fn (string $contents) => $this->parseCsv($contents), $service, $service);
    $normalizer = \Closure::bind(fn (array $record) => $this->normalizeRecord($record), $service, $service);

    $record = $normalizer($parser($csv)[0]);

    expect($record)
        ->not->toBeNull()
        ->and($record['start_date'])->toBe('2026-09-30')
        ->and($record['end_date'])->toBe('2026-09-30')
        ->and($record['reason'])->toBe('ซิงค์จาก Google Sheet');
});
