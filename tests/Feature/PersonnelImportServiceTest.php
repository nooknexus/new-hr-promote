<?php

use App\Services\PersonnelImportService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

beforeEach(function () {
    Schema::create('person', function ($table) {
        $table->string('cid', 13)->primary();
        $table->string('pname')->nullable();
        $table->string('fname')->nullable();
        $table->string('lname')->nullable();
        $table->unsignedTinyInteger('type_id')->nullable();
        $table->string('type_name')->nullable();
        $table->string('office_id', 2)->nullable();
        $table->string('office_name')->nullable();
        $table->date('employment_started_at')->nullable();
    });
});

test('it imports a personnel workbook by creating new personnel and updating an existing CID', function () {
    DB::table('person')->insert([
        'cid' => '1234567890123',
        'pname' => 'นาย',
        'fname' => 'ข้อมูลเดิม',
        'lname' => 'เดิม',
        'type_name' => 'ข้าราชการ',
    ]);

    $spreadsheet = new Spreadsheet;
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->fromArray([
        ['เลขประจำตัวประชาชน', 'คำนำหน้า', 'ชื่อ', 'นามสกุล', 'รหัสประเภท', 'ประเภทเจ้าหน้าที่', 'รหัสหน่วยงาน', 'หน่วยงาน', 'วันเริ่มงาน'],
        ['1234567890123', 'นาง', 'แก้ไขแล้ว', 'บุคลากร', 1, 'ข้าราชการ', '01', 'สำนักงานสาธารณสุข', '2015-05-01'],
        ['9876543210987', 'นาย', 'เพิ่มใหม่', 'ทดสอบ', 2, 'พนักงานราชการ', '02', 'โรงพยาบาล', '2026-03-01'],
    ]);
    $temporaryPath = tempnam(sys_get_temp_dir(), 'personnel-import-');
    $path = $temporaryPath.'.xlsx';
    @unlink($temporaryPath);
    (new Xlsx($spreadsheet))->save($path);

    $result = (new PersonnelImportService)->import($path);

    expect($result)->toMatchArray(['created' => 1, 'updated' => 1, 'skipped' => 0])
        ->and(DB::table('person')->where('cid', '1234567890123')->value('fname'))->toBe('แก้ไขแล้ว')
        ->and(DB::table('person')->where('cid', '1234567890123')->value('employment_started_at'))->toBe('2015-05-01')
        ->and(DB::table('person')->where('cid', '9876543210987')->value('type_name'))->toBe('พนักงานราชการ');

    @unlink($path);
});
