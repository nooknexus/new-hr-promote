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
        $table->string('no_position')->nullable();
        $table->string('position')->nullable();
        $table->string('position_level')->nullable();
    });
    Schema::create('users', function ($table) {
        $table->id();
        $table->string('pid', 13)->nullable();
        $table->string('thaid_pid', 13)->nullable();
        $table->string('no_position')->nullable();
    });
    seedOffices(['01' => 'สำนักงานสาธารณสุข', '02' => 'โรงพยาบาล']);
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

/** @param array<int, array{0: string, 1: string, 2: string, 3: ?string, 4: ?string, 5: string}> $rows cid, fname, lname, office_id, office_name, start date */
function personnelWorkbook(array $rows): string
{
    $spreadsheet = new Spreadsheet;
    $spreadsheet->getActiveSheet()->fromArray([
        ['เลขประจำตัวประชาชน', 'คำนำหน้า', 'ชื่อ', 'นามสกุล', 'รหัสประเภท', 'ประเภทเจ้าหน้าที่', 'รหัสหน่วยงาน', 'หน่วยงาน', 'วันเริ่มงาน'],
        ...array_map(fn (array $row) => [$row[0], null, $row[1], $row[2], null, null, $row[3], $row[4], $row[5]], $rows),
    ]);
    $temporaryPath = tempnam(sys_get_temp_dir(), 'personnel-import-');
    $path = $temporaryPath.'.xlsx';
    @unlink($temporaryPath);
    (new Xlsx($spreadsheet))->save($path);

    return $path;
}

test('an office-scoped import rejects rows for another office or personnel owned by another office', function () {
    DB::table('person')->insert(['cid' => '2222222222222', 'fname' => 'หน่วยสอง', 'lname' => 'เดิม', 'office_id' => '02']);

    foreach ([
        ['1111111111111', 'ใหม่', 'ทดสอบ', '02', 'หน่วยสอง', '2020-01-01'],
        ['2222222222222', 'แก้ไข', 'ทดสอบ', '01', 'หน่วยหนึ่ง', '2020-01-01'],
    ] as $row) {
        $path = personnelWorkbook([$row]);

        expect(fn () => (new PersonnelImportService)->import($path, '01'))
            ->toThrow(RuntimeException::class, 'นำเข้าได้เฉพาะบุคลากรของหน่วยงานตนเอง');

        @unlink($path);
    }

    expect(DB::table('person')->where('cid', '1111111111111')->exists())->toBeFalse()
        ->and(DB::table('person')->where('cid', '2222222222222')->value('office_id'))->toBe('02');
});

test('an office-scoped import assigns blank office cells to the importing office', function () {
    $path = personnelWorkbook([['1111111111111', 'ใหม่', 'ทดสอบ', null, null, '2020-01-01']]);

    $result = (new PersonnelImportService)->import($path, '01');

    expect($result['created'])->toBe(1)
        ->and(DB::table('person')->where('cid', '1111111111111')->value('office_id'))->toBe('01');

    @unlink($path);
});

test('it reads columns A to I by position regardless of the header text', function () {
    $spreadsheet = new Spreadsheet;
    $spreadsheet->getActiveSheet()->fromArray([
        ['CID', 'Prefix', 'First', 'Last', 'TypeId', 'Type', 'OfficeId', 'Office', 'Start'],
        ['1234567890123', 'นาง', 'ตามตำแหน่ง', 'คอลัมน์', 1, 'ข้าราชการ', '01', 'สสจ.', '2020-01-15'],
    ]);
    $temporaryPath = tempnam(sys_get_temp_dir(), 'personnel-import-');
    $path = $temporaryPath.'.xlsx';
    @unlink($temporaryPath);
    (new Xlsx($spreadsheet))->save($path);

    $result = (new PersonnelImportService)->import($path);

    expect($result['created'])->toBe(1)
        ->and((array) DB::table('person')->first())->toMatchArray([
            'cid' => '1234567890123',
            'pname' => 'นาง',
            'fname' => 'ตามตำแหน่ง',
            'lname' => 'คอลัมน์',
            'type_name' => 'ข้าราชการ',
            'office_id' => '01',
            // The office name comes from the offices registry, not column H.
            'office_name' => 'สำนักงานสาธารณสุข',
            'employment_started_at' => '2020-01-15',
        ]);

    @unlink($path);
});

test('a personnel import rejects office codes missing from the registry or inactive', function () {
    seedOffices(['09' => 'ต้นสังกัดปิด'], active: false);

    foreach ([['1111111111111', 'ใหม่', 'ทดสอบ', '77', 'ไม่มีในทะเบียน', '2020-01-01'], ['1111111111111', 'ใหม่', 'ทดสอบ', '09', 'ปิด', '2020-01-01']] as $row) {
        $path = personnelWorkbook([$row]);

        expect(fn () => (new PersonnelImportService)->import($path))
            ->toThrow(RuntimeException::class, 'ไม่พบรหัสต้นสังกัด');

        @unlink($path);
    }

    expect(DB::table('person')->count())->toBe(0);
});

test('numeric office codes from Excel are read as two-digit codes', function () {
    seedOffices(['05' => 'โรงพยาบาลห้า']);
    $path = personnelWorkbook([['1111111111111', 'ใหม่', 'ทดสอบ', 5, null, '2020-01-01']]);

    (new PersonnelImportService)->import($path);

    expect(DB::table('person')->first(['office_id', 'office_name']))
        ->toMatchObject(['office_id' => '05', 'office_name' => 'โรงพยาบาลห้า']);

    @unlink($path);
});

test('it imports position, level and position number from columns J to L', function () {
    DB::table('person')->insert([
        'cid' => '1234567890123', 'fname' => 'เดิม', 'lname' => 'บุคลากร',
        'position' => 'นักวิชาการสาธารณสุข', 'position_level' => 'ปฏิบัติการ', 'no_position' => '1001',
    ]);
    DB::table('person')->insert([
        'cid' => '2222222222222', 'fname' => 'คงเดิม', 'lname' => 'บุคลากร',
        'position' => 'พยาบาลวิชาชีพ', 'position_level' => 'ชำนาญการ', 'no_position' => '2002',
    ]);
    DB::table('users')->insert([
        ['id' => 10, 'pid' => '1234567890123', 'thaid_pid' => null, 'no_position' => '1001'],
        ['id' => 11, 'pid' => '2222222222222', 'thaid_pid' => null, 'no_position' => '2002'],
    ]);

    $spreadsheet = new Spreadsheet;
    $spreadsheet->getActiveSheet()->fromArray([
        ['CID', 'Prefix', 'First', 'Last', 'TypeId', 'Type', 'OfficeId', 'Office', 'Start', 'Position', 'Level', 'No'],
        ['1234567890123', null, 'เดิม', 'บุคลากร', null, null, null, null, null, ' นักวิชาการสาธารณสุข ', 'ชำนาญการ', 1005],
        ['2222222222222', null, 'คงเดิม', 'บุคลากร', null, null, null, null, null, null, null, null],
        ['9876543210987', null, 'ใหม่', 'บุคลากร', null, null, null, null, null, 'เจ้าพนักงานธุรการ', 'ปฏิบัติงาน', '0123'],
    ]);
    $temporaryPath = tempnam(sys_get_temp_dir(), 'personnel-import-');
    $path = $temporaryPath.'.xlsx';
    @unlink($temporaryPath);
    (new Xlsx($spreadsheet))->save($path);

    expect((new PersonnelImportService)->import($path))->toMatchArray(['created' => 1, 'updated' => 2]);

    $positions = fn (string $cid) => (array) DB::table('person')->where('cid', $cid)->first(['position', 'position_level', 'no_position']);
    expect($positions('1234567890123'))->toBe(['position' => 'นักวิชาการสาธารณสุข', 'position_level' => 'ชำนาญการ', 'no_position' => '1005'])
        // Blank cells keep the current values.
        ->and($positions('2222222222222'))->toBe(['position' => 'พยาบาลวิชาชีพ', 'position_level' => 'ชำนาญการ', 'no_position' => '2002'])
        ->and($positions('9876543210987'))->toBe(['position' => 'เจ้าพนักงานธุรการ', 'position_level' => 'ปฏิบัติงาน', 'no_position' => '0123'])
        // Payslips read the number from the linked account.
        ->and(DB::table('users')->where('id', 10)->value('no_position'))->toBe('1005')
        ->and(DB::table('users')->where('id', 11)->value('no_position'))->toBe('2002');

    @unlink($path);
});

test('the downloadable personnel template has columns J to L for positions', function () {
    $sheet = \PhpOffice\PhpSpreadsheet\IOFactory::load(resource_path('templates/personnel-import-template.xlsx'))->getSheet(0);

    expect($sheet->rangeToArray('J1:L1')[0])->toBe(['ตำแหน่ง', 'ระดับ', 'เลขที่ตำแหน่ง'])
        ->and($sheet->getStyle('L2')->getNumberFormat()->getFormatCode())->toBe('@');
});
