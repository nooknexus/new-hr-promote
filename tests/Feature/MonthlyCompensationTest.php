<?php

use App\Livewire\Admin\CompensationIssuance;
use App\Livewire\Admin\MonthlyCompensationManagement;
use App\Livewire\Admin\TaxCertificateManagement;
use App\Models\User;
use App\Services\CompensationCertificateIssuer;
use App\Services\MonthlyCompensationImportService;
use App\Services\MonthlyCompensationService;
use App\Services\TaxCertificatePdfService;
use App\Support\CompensationItems;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpKernel\Exception\HttpException;

const CIVIL = '1111111111111';
const GOV_EMPLOYEE = '3333333333333';
const MINISTRY = '4444444444444';
const PERMANENT = '2222222222222';
const OTHER_OFFICE = '5555555555555';

beforeEach(function () {
    Schema::create('person', function (Blueprint $table) {
        $table->string('cid', 13)->primary();
        $table->string('pname')->nullable();
        $table->string('fname')->nullable();
        $table->string('lname')->nullable();
        $table->unsignedTinyInteger('type_id')->nullable();
        $table->string('type_name')->nullable();
        $table->string('office_id', 2)->nullable();
        $table->string('office_name')->nullable();
    });
    Schema::create('users', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('role')->nullable();
        $table->string('office', 2)->nullable();
        $table->boolean('is_active')->default(true);
        $table->string('pid', 13)->nullable();
        $table->string('thaid_pid', 13)->nullable();
        $table->text('address')->nullable();
    });
    Schema::create('data_tax', function (Blueprint $table) {
        $table->string('cid', 13);
        $table->string('income_year', 4);
        $table->string('source', 3)->default('cgd');
        foreach (['running_code1', 'department_name', 'department_tax_id', 'department_addr', 'tax_id', 'fullname', 'addr', 'running_code2',
            'income_type_id', 'income_type_name', 'income_money', 'withholding_tax', 'total_money', 'sumTaxAmount', 'sumTaxText',
            'outcomeType', 'outcomeName', 'outcomeAmount', 'tax_type_id', 'tax_type_name', 'date_report', 'socialSecurity'] as $column) {
            $table->string($column)->nullable();
        }
        $table->dateTime('r_update')->nullable();
        $table->primary(['cid', 'income_year', 'source']);
    });
    (require database_path('migrations/2026_10_11_110000_create_monthly_compensation_tables.php'))->up();

    DB::table('person')->insert([
        ['cid' => CIVIL, 'pname' => 'นาย', 'fname' => 'ข้าราชการ', 'lname' => 'ทดสอบ', 'type_id' => 1, 'type_name' => 'ข้าราชการ', 'office_id' => '01', 'office_name' => 'หน่วยหนึ่ง'],
        ['cid' => PERMANENT, 'pname' => 'นาย', 'fname' => 'ลูกจ้างประจำ', 'lname' => 'ทดสอบ', 'type_id' => 2, 'type_name' => 'ลูกจ้างประจำ', 'office_id' => '01', 'office_name' => 'หน่วยหนึ่ง'],
        ['cid' => GOV_EMPLOYEE, 'pname' => 'นาง', 'fname' => 'พนักงานราชการ', 'lname' => 'ทดสอบ', 'type_id' => 3, 'type_name' => 'พนักงานราชการ', 'office_id' => '01', 'office_name' => 'หน่วยหนึ่ง'],
        ['cid' => MINISTRY, 'pname' => 'นางสาว', 'fname' => 'พนักงานกระทรวง', 'lname' => 'ทดสอบ', 'type_id' => 4, 'type_name' => 'พนักงานกระทรวง', 'office_id' => '01', 'office_name' => 'หน่วยหนึ่ง'],
        ['cid' => OTHER_OFFICE, 'pname' => 'นาย', 'fname' => 'ต่างหน่วย', 'lname' => 'ทดสอบ', 'type_id' => 3, 'type_name' => 'พนักงานราชการ', 'office_id' => '02', 'office_name' => 'หน่วยสอง'],
    ]);
    seedOffices(['00' => 'สสจ.', '01' => 'หน่วยหนึ่ง', '02' => 'หน่วยสอง']);
    DB::table('users')->insert([
        ['id' => 1, 'name' => 'ผู้ดูแล', 'role' => 'SUPERADMIN', 'office' => null, 'pid' => null, 'address' => null],
        ['id' => 2, 'name' => 'การเงินหน่วยหนึ่ง', 'role' => 'FINANCE', 'office' => '01', 'pid' => null, 'address' => null],
        ['id' => 3, 'name' => 'การเงินหน่วยสอง', 'role' => 'FINANCE', 'office' => '02', 'pid' => null, 'address' => null],
        ['id' => 4, 'name' => 'การเงิน สสจ.', 'role' => 'FINANCE', 'office' => '00', 'pid' => null, 'address' => null],
        ['id' => 5, 'name' => 'พนักงานกระทรวง', 'role' => null, 'office' => null, 'pid' => MINISTRY, 'address' => '99 ถนนทดสอบ เมือง พิษณุโลก'],
    ]);
});

function compensationUser(int $id): User
{
    return User::find($id);
}

function setPayer(): void
{
    DB::table('tax_payer_settings')->insert(['name' => 'สำนักงานสาธารณสุขจังหวัดพิษณุโลก', 'tax_id' => '0994000000000', 'address' => '1 ถนนสาธารณสุข พิษณุโลก']);
}

/** @param list<list<mixed>> $rows */
function compensationWorkbook(array $rows): string
{
    $book = new Spreadsheet;
    $sheet = $book->getActiveSheet();
    $sheet->fromArray(MonthlyCompensationImportService::headers(), null, 'A1');
    foreach ($rows as $index => $row) {
        foreach ($row as $column => $value) {
            $column === 0
                ? $sheet->setCellValueExplicit([1, $index + 2], $value, DataType::TYPE_STRING)
                : $sheet->setCellValue([$column + 1, $index + 2], $value);
        }
    }
    $path = tempnam(sys_get_temp_dir(), 'compensation').'.xlsx';
    (new Xlsx($book))->save($path);

    return $path;
}

/** One import row: cid, name, then amounts keyed by CompensationItems field (and other_name). */
function compensationRow(string $cid, array $values): array
{
    $row = [$cid, 'ชื่อ'];
    foreach (CompensationItems::ITEMS as $field => $item) {
        $row[] = $values[$field] ?? '';
        if ($field === 'other_amount') {
            $row[] = $values['other_name'] ?? '';
        }
    }

    return $row;
}

test('items are limited to their personnel types and permanent employees are not keyed', function () {
    $service = app(MonthlyCompensationService::class);
    $finance = compensationUser(2);

    $service->save($finance, CIVIL, 2568, 1, ['pts' => 1500, 'non_practice' => 10000, 'withholding_tax' => 300]);
    $service->save($finance, GOV_EMPLOYEE, 2568, 1, ['salary' => 18000, 'kts' => 2000, 'social_security' => 750]);
    $service->save($finance, MINISTRY, 2568, 1, ['salary' => 15000, 'social_security' => 750, 'provident_fund' => 450]);

    expect(fn () => $service->save($finance, GOV_EMPLOYEE, 2568, 2, ['pts' => 1500]))->toThrow(ValidationException::class)
        ->and(fn () => $service->save($finance, CIVIL, 2568, 2, ['salary' => 1000]))->toThrow(ValidationException::class)
        ->and(fn () => $service->save($finance, GOV_EMPLOYEE, 2568, 2, ['provident_fund' => 100]))->toThrow(ValidationException::class)
        ->and(fn () => $service->save($finance, PERMANENT, 2568, 2, ['back_pay' => 100]))->toThrow(ValidationException::class)
        ->and(fn () => $service->save($finance, CIVIL, 2568, 2, ['other_amount' => 100]))->toThrow(ValidationException::class);

    $service->save($finance, CIVIL, 2568, 2, ['other_amount' => 100, 'other_name' => 'ค่าเวร']);
    expect(DB::table('monthly_compensations')->count())->toBe(4)
        ->and(DB::table('monthly_compensations')->where('cid', CIVIL)->where('month', 1)->value('office_id'))->toBe('01');
});

test('offices key only their own personnel and keep the months they keyed after a move', function () {
    $service = app(MonthlyCompensationService::class);

    expect(fn () => $service->save(compensationUser(2), OTHER_OFFICE, 2568, 1, ['salary' => 100]))->toThrow(ValidationException::class);

    $id = $service->save(compensationUser(2), GOV_EMPLOYEE, 2568, 1, ['salary' => 18000]);
    DB::table('person')->where('cid', GOV_EMPLOYEE)->update(['office_id' => '02']);

    $service->save(compensationUser(2), GOV_EMPLOYEE, 2568, 1, ['salary' => 18500]);
    expect(DB::table('monthly_compensations')->where('id', $id)->value('salary'))->toEqual('18500.00');

    expect(fn () => $service->save(compensationUser(3), GOV_EMPLOYEE, 2568, 1, ['salary' => 1]))->toThrow(HttpException::class);
    $service->save(compensationUser(3), GOV_EMPLOYEE, 2568, 2, ['salary' => 19000]);
    expect(DB::table('monthly_compensations')->where('cid', GOV_EMPLOYEE)->where('month', 2)->value('office_id'))->toBe('02');

    Livewire::actingAs(compensationUser(3))->test(MonthlyCompensationManagement::class)
        ->set('year', 2568)->set('month', 1)->assertDontSee('พนักงานราชการ ทดสอบ');
});

test('confirming a year locks the office until the provincial office reopens it', function () {
    $service = app(MonthlyCompensationService::class);
    $service->save(compensationUser(2), CIVIL, 2568, 1, ['pts' => 1500]);

    expect(fn () => $service->confirm(compensationUser(3), 2568, '01'))->toThrow(HttpException::class);
    $service->confirm(compensationUser(2), 2568, '01');

    expect(fn () => $service->save(compensationUser(2), CIVIL, 2568, 2, ['pts' => 1500]))->toThrow(ValidationException::class)
        ->and(fn () => $service->unconfirm(compensationUser(2), 2568, '01'))->toThrow(HttpException::class);

    $service->unconfirm(compensationUser(4), 2568, '01');
    $service->save(compensationUser(2), CIVIL, 2568, 2, ['pts' => 1500]);
    expect(DB::table('monthly_compensations')->count())->toBe(2);
});

test('a monthly import replaces people in the file, skips ineligible rows, and keeps others', function () {
    $service = app(MonthlyCompensationService::class);
    $service->save(compensationUser(2), MINISTRY, 2568, 3, ['salary' => 15000]);
    $service->save(compensationUser(2), CIVIL, 2568, 3, ['pts' => 1000]);
    $path = compensationWorkbook([
        compensationRow(GOV_EMPLOYEE, ['salary' => '18,000', 'kts' => 2000, 'social_security' => 750]),
        compensationRow(MINISTRY, ['salary' => 15500, 'provident_fund' => 465]),
        compensationRow(OTHER_OFFICE, ['salary' => 1]),
        compensationRow(PERMANENT, ['back_pay' => 1]),
        compensationRow(CIVIL, ['salary' => 1]),
        compensationRow('9999999999999', ['salary' => 1]),
        compensationRow(PERMANENT, []),
        compensationRow(CIVIL, ['pts' => '0', 'other_name' => 'ไม่มียอด']),
    ]);
    $importer = app(MonthlyCompensationImportService::class);

    $preview = $importer->preview($path, compensationUser(2), 2568, 3);
    expect($preview['insert_count'])->toBe(1)->and($preview['update_count'])->toBe(1)
        ->and($preview['errors'])->toBe([])->and($preview['skipped'])->toHaveCount(4)
        ->and($preview['empty_count'])->toBe(2)
        ->and(implode(' ', $preview['skipped']))->not->toContain(OTHER_OFFICE);

    $result = $importer->commit($path, compensationUser(2), 2568, 3, $preview['file_hash']);
    expect($result)->toBe(['inserted' => 1, 'updated' => 1, 'skipped' => 4, 'empty' => 2])
        ->and(DB::table('monthly_compensations')->where('cid', MINISTRY)->value('salary'))->toEqual('15500.00')
        // The CIVIL row with only zeros left the stored month untouched.
        ->and(DB::table('monthly_compensations')->where('cid', CIVIL)->value('pts'))->toEqual('1000.00')
        ->and(DB::table('monthly_compensations')->where('cid', OTHER_OFFICE)->exists())->toBeFalse();

    $bad = compensationWorkbook([compensationRow('123', ['salary' => 'abc'])]);
    expect($importer->preview($bad, compensationUser(2), 2568, 3)['errors'])->toHaveCount(2);
    expect(fn () => $importer->commit($path, compensationUser(2), 2568, 3, 'changed'))->toThrow(RuntimeException::class);
});

test('the import template lists the office personnel of keyed types under the expected headers', function () {
    $path = app(MonthlyCompensationImportService::class)->template(compensationUser(2));
    $sheet = PhpOffice\PhpSpreadsheet\IOFactory::load($path)->getActiveSheet();

    expect($sheet->rangeToArray('A1:L1')[0])->toBe(MonthlyCompensationImportService::headers())
        ->and(array_column($sheet->toArray(), 0))->toContain(CIVIL, GOV_EMPLOYEE, MINISTRY)
        ->and(array_column($sheet->toArray(), 0))->not->toContain(PERMANENT, OTHER_OFFICE);
});

test('issuing totals every keyed month into provincial office certificates and locks the year', function () {
    $service = app(MonthlyCompensationService::class);
    $issuer = app(CompensationCertificateIssuer::class);
    foreach ([1, 2] as $month) {
        $service->save(compensationUser(2), MINISTRY, 2568, $month, ['salary' => 15000, 'withholding_tax' => 100.5, 'social_security' => 750, 'provident_fund' => 450]);
        $service->save(compensationUser(2), CIVIL, 2568, $month, ['pts' => 1500]);
    }
    $service->save(compensationUser(3), OTHER_OFFICE, 2568, 1, ['salary' => 0]);

    expect(fn () => $issuer->issue(compensationUser(4), 2568, '15012569'))->toThrow(ValidationException::class);
    setPayer();
    expect(fn () => $issuer->issue(compensationUser(4), 2568, '15012569'))->toThrow(ValidationException::class)
        ->and(fn () => $issuer->issue(compensationUser(2), 2568, '15012569', true))->toThrow(HttpException::class);

    $service->confirm(compensationUser(2), 2568, '01');
    expect($issuer->issue(compensationUser(4), 2568, '15012569', skipUnconfirmed: true))->toBe(2);

    $ministry = DB::table('data_tax')->where('cid', MINISTRY)->where('source', 'pho')->first();
    expect($ministry)->toMatchObject([
        'income_year' => '2568', 'running_code1' => '6801002', 'department_name' => 'สำนักงานสาธารณสุขจังหวัดพิษณุโลก',
        'income_money' => '30000.00', 'withholding_tax' => '201.00', 'sumTaxText' => 'สองร้อยเอ็ดบาทถ้วน',
        'socialSecurity' => '1500.00', 'outcomeType' => '2', 'outcomeName' => 'กสล.พกส',
        'outcomeAmount' => '900.00', 'income_type_name' => 'ภ.ง.ด.1ก พิเศษ', 'tax_type_name' => 'หัก ณ ที่จ่าย',
        'addr' => '99 ถนนทดสอบ เมือง พิษณุโลก', 'date_report' => '15012569',
    ]);
    expect(DB::table('data_tax')->where('cid', CIVIL)->first())->toMatchObject([
        'running_code1' => '6801001', 'income_money' => '3000.00', 'addr' => '1 ถนนสาธารณสุข พิษณุโลก', 'outcomeType' => null,
    ]);
    expect(DB::table('data_tax')->where('cid', OTHER_OFFICE)->exists())->toBeFalse()
        ->and(fn () => $service->save(compensationUser(2), CIVIL, 2568, 3, ['pts' => 1]))->toThrow(ValidationException::class);

    $pdf = TaxCertificatePdfService::generatePdf(TaxCertificatePdfService::fromTaxRecord($ministry));
    expect($pdf['content'])->toStartWith('%PDF');
});

test('reopening clears confirmations, keeps old certificates until reissued, and keeps numbers', function () {
    $service = app(MonthlyCompensationService::class);
    $issuer = app(CompensationCertificateIssuer::class);
    setPayer();
    $service->save(compensationUser(2), CIVIL, 2568, 1, ['pts' => 1500]);
    $service->save(compensationUser(2), MINISTRY, 2568, 1, ['salary' => 15000]);
    $service->confirm(compensationUser(2), 2568, '01');
    $issuer->issue(compensationUser(1), 2568, '15012569');

    $issuer->reopen(compensationUser(4), 2568);
    expect(DB::table('compensation_year_confirmations')->count())->toBe(0)
        ->and(DB::table('data_tax')->where('source', 'pho')->count())->toBe(2);

    $service->save(compensationUser(2), CIVIL, 2568, 1, ['pts' => 2000]);
    DB::table('monthly_compensations')->where('cid', MINISTRY)->delete();
    $issuer->issue(compensationUser(4), 2568, '20012569', skipUnconfirmed: true);

    expect(DB::table('data_tax')->where('source', 'pho')->pluck('running_code1', 'cid')->all())->toBe([CIVIL => '6801001'])
        ->and(DB::table('data_tax')->where('cid', CIVIL)->value('income_money'))->toBe('2000.00');
});

test('certificates from both payers coexist and the certificate tab manages only the Comptroller General ones', function () {
    DB::table('data_tax')->insert([
        ['cid' => CIVIL, 'income_year' => '2568', 'source' => 'cgd', 'running_code1' => '151427', 'department_name' => 'สำนักงานปลัดกระทรวงสาธารณสุข', 'income_money' => '465040.00'],
        ['cid' => CIVIL, 'income_year' => '2568', 'source' => 'pho', 'running_code1' => '6801001', 'department_name' => 'สำนักงานสาธารณสุขจังหวัดพิษณุโลก', 'income_money' => '3000.00'],
    ]);

    Livewire::withQueryParams(['tab' => 'certificates'])->actingAs(compensationUser(1))->test(TaxCertificateManagement::class)
        ->assertSee('151427')->assertDontSee('6801001')
        ->assertSee('ค่าตอบแทนรายเดือน')->assertSee('ออกหนังสือรับรอง สสจ.');
});

test('only the provincial office sees the issuance tab', function () {
    Livewire::withQueryParams(['tab' => 'certificates'])->actingAs(compensationUser(2))->test(TaxCertificateManagement::class)
        ->assertSee('ค่าตอบแทนรายเดือน')->assertDontSee('ออกหนังสือรับรอง สสจ.')
        ->assertSet('tab', 'monthly')->assertDontSee('หนังสือรับรอง (กรมบัญชีกลาง)')
        ->set('tab', 'issuance')->assertSet('tab', 'monthly');
    Livewire::actingAs(compensationUser(2))->test(CompensationIssuance::class)->assertForbidden();
    Livewire::actingAs(compensationUser(4))->test(CompensationIssuance::class)->assertOk()->assertSee('ข้อมูลผู้จ่ายเงิน');
    // Tabs are full page loads so each tab's modal and toast scripts initialise.
    Livewire::withQueryParams(['tab' => 'certificates'])->actingAs(compensationUser(4))->test(TaxCertificateManagement::class)
        ->assertSeeHtml('href="'.route('admin.tax-certificates.index', ['tab' => 'issuance']).'"');
});

test('the monthly form saves through a modal and reports type problems', function () {
    Livewire::actingAs(compensationUser(2))->test(MonthlyCompensationManagement::class)
        ->set('year', 2568)->set('month', 4)
        ->call('openCreate')->assertDispatched('compensation-form-opened')
        ->set('personSearch', 'พนักงานราชการ')
        ->assertSee('พนักงานราชการ ทดสอบ')
        ->assertDontSee('ต่างหน่วย')
        ->call('selectPerson', GOV_EMPLOYEE)
        ->assertSeeHtml('id="compensation-kts"')
        ->assertDontSeeHtml('id="compensation-pts"')
        ->set('amounts.salary', '18000')
        ->set('amounts.pts', '100')
        ->call('save')
        ->assertHasErrors('amounts')
        ->set('amounts.pts', '')
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('compensation-form-closed')
        ->assertDispatched('compensation-saved', message: 'เพิ่มค่าตอบแทนรายเดือนแล้ว');

    expect(DB::table('monthly_compensations')->where('cid', GOV_EMPLOYEE)->value('salary'))->toEqual('18000.00');
});

test('the payer settings are validated and saved by the provincial office', function () {
    Livewire::actingAs(compensationUser(4))->test(CompensationIssuance::class)
        ->set('payerName', 'สำนักงานสาธารณสุขจังหวัดพิษณุโลก')->set('payerTaxId', '123')->set('payerAddress', 'ที่อยู่')
        ->call('savePayer')->assertHasErrors('payerTaxId')
        ->set('payerTaxId', '0994000000000')->call('savePayer')->assertHasNoErrors()
        ->assertDispatched('issuance-saved');

    expect(DB::table('tax_payer_settings')->value('tax_id'))->toBe('0994000000000');
});

test('the monthly tab previews and confirms an Excel import and the template downloads', function () {
    $path = compensationWorkbook([compensationRow(GOV_EMPLOYEE, ['salary' => 18000]), compensationRow(MINISTRY, [])]);
    $file = Illuminate\Http\UploadedFile::fake()->createWithContent('compensation.xlsx', file_get_contents($path));

    Livewire::actingAs(compensationUser(2))->test(MonthlyCompensationManagement::class)
        ->set('year', 2568)->set('month', 5)
        ->set('importFile', $file)
        ->call('previewImport')
        ->assertSet('importPreview.insert', 1)
        ->assertSee('เพิ่มใหม่ 1')
        ->assertSee('ไม่มียอด (ไม่นำเข้า) 1')
        ->call('confirmImport')
        ->assertHasNoErrors()
        ->assertDispatched('compensation-saved', message: 'นำเข้าค่าตอบแทน พฤษภาคม 2568 แล้ว เพิ่ม 1 ปรับปรุง 0 ข้าม 0 ไม่มียอด 1 รายการ');

    expect(DB::table('monthly_compensations')->where('cid', MINISTRY)->exists())->toBeFalse();

    expect(DB::table('monthly_compensations')->where('month', 5)->value('salary'))->toEqual('18000.00');

    $this->actingAs(compensationUser(2))->get(route('admin.tax-certificates.compensation-template'))
        ->assertOk()->assertDownload('monthly-compensation-template.xlsx');
    $this->actingAs(compensationUser(5))->get(route('admin.tax-certificates.compensation-template'))->assertForbidden();
});

test('the year confirmation dialogs name the office instead of showing its code', function () {
    app(MonthlyCompensationService::class)->save(compensationUser(2), CIVIL, 2568, 1, ['pts' => 1500]);

    Livewire::actingAs(compensationUser(2))->test(MonthlyCompensationManagement::class)
        ->set('year', 2568)
        ->call('prepareConfirmYear', '01')
        ->assertSeeText('ของต้นสังกัด หน่วยหนึ่ง ครบถ้วนแล้ว');

    app(MonthlyCompensationService::class)->confirm(compensationUser(2), 2568, '01');
    Livewire::actingAs(compensationUser(4))->test(CompensationIssuance::class)
        ->set('year', 2568)
        ->call('prepareUnconfirm', '01')
        ->assertSeeText('ให้ หน่วยหนึ่ง แก้ไขข้อมูลปี 2568')
        ->call('unconfirm')
        ->assertDispatched('issuance-saved', message: 'ปลดการยืนยันของหน่วยหนึ่งแล้ว');
});

test('the monthly tab shows office names instead of codes', function () {
    app(MonthlyCompensationService::class)->save(compensationUser(2), CIVIL, 2568, 1, ['pts' => 1500]);

    Livewire::actingAs(compensationUser(2))->test(MonthlyCompensationManagement::class)
        ->set('year', 2568)->set('month', 1)
        ->assertSeeHtml('<td class="px-4 py-3">หน่วยหนึ่ง</td>')
        ->assertDontSee('01 — หน่วยหนึ่ง')
        ->assertDontSeeHtml('<td class="whitespace-nowrap px-4 py-3">01</td>');

    Livewire::actingAs(compensationUser(4))->test(CompensationIssuance::class)
        ->set('year', 2568)
        ->assertSeeHtml('<td class="px-4 py-3">หน่วยหนึ่ง</td>')
        ->assertDontSee('01 — หน่วยหนึ่ง');
});

test('monthly compensation is the main tab and the certificate tab comes last', function () {
    $html = Livewire::actingAs(compensationUser(4))->test(TaxCertificateManagement::class)
        ->assertSet('tab', 'monthly')->html();

    $monthly = strpos($html, 'ค่าตอบแทนรายเดือน');
    $issuance = strpos($html, 'ออกหนังสือรับรอง สสจ.');
    $certificates = strpos($html, 'หนังสือรับรอง (กรมบัญชีกลาง)');
    expect($monthly)->toBeLessThan($issuance)->and($issuance)->toBeLessThan($certificates);
});

test('the confirmation status panel comes before the monthly compensation panel', function () {
    $html = Livewire::actingAs(compensationUser(2))->test(MonthlyCompensationManagement::class)->html();

    expect(strpos($html, 'id="compensation-confirm-heading"'))->toBeLessThan(strpos($html, 'id="compensation-period-heading"'));
});

test('certificate numbers are year, office code and a running number per office', function () {
    $service = app(MonthlyCompensationService::class);
    setPayer();
    $service->save(compensationUser(2), CIVIL, 2567, 1, ['pts' => 1500]);
    $service->save(compensationUser(2), MINISTRY, 2567, 1, ['salary' => 15000]);
    $service->save(compensationUser(3), OTHER_OFFICE, 2567, 1, ['salary' => 18000]);
    // Moved to office 02 mid-year: the office of the latest month gives the number.
    $service->save(compensationUser(2), GOV_EMPLOYEE, 2567, 1, ['salary' => 18000]);
    DB::table('person')->where('cid', GOV_EMPLOYEE)->update(['office_id' => '02']);
    $service->save(compensationUser(3), GOV_EMPLOYEE, 2567, 6, ['salary' => 18000]);

    app(CompensationCertificateIssuer::class)->issue(compensationUser(1), 2567, '15012568', skipUnconfirmed: true);

    expect(DB::table('data_tax')->where('source', 'pho')->orderBy('cid')->pluck('running_code1', 'cid')->all())->toBe([
        CIVIL => '6701001',
        GOV_EMPLOYEE => '6702001',
        MINISTRY => '6701002',
        OTHER_OFFICE => '6702002',
    ]);
});
