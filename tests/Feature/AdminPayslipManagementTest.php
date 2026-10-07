<?php

use App\Livewire\Admin\PayslipManagement;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

beforeEach(function () {
    Schema::create('person', function ($table) {
        $table->string('cid', 13)->primary();
        $table->string('pname')->nullable();
        $table->string('fname')->nullable();
        $table->string('lname')->nullable();
        $table->string('office_id', 2)->nullable();
        $table->string('office_name')->nullable();
    });

    Schema::create('data_salary', function ($table) {
        $table->string('cid', 13);
        $table->string('yearNO');
        $table->string('monthNO', 2);
        $table->timestamp('r_update')->nullable();

        foreach ([
            'ministry', 'depart', 'bankId', 'bankName', 'bankBranchId', 'bankBranchName', 'bankNo', 'verifyDate',
            'incomeSalaryAmount', 'incomeSalaryBackPayAmount', 'incomeManagerMoneyAmount', 'incomeManagerMoneyBackPayAmount',
            'incomePositionKingAmount', 'incomeWarAmount', 'incomeCourseFeeAmount', 'incomeBreakLawAmount',
            'incomeSpecialPlaceAmount', 'incomeSouthAmount', 'incomePositionMoneyAmount', 'incomePositionMoneyBackPayAmount',
            'incomeLv8Amount', 'incomeLv8BackPayAmount', 'incomeLv1to7Amount', 'incomeSpecialHightAmount',
            'incomeHouseRentAmount', 'incomeChildFeeAmount', 'incomeChildEducationFeeAmount', 'incomeRewardAmount',
            'incomeAdd1Name', 'incomeAdd1Amount', 'incomeAdd2Name', 'incomeAdd2Amount', 'incomeAdd3Name', 'incomeAdd3Amount',
            'incomeAdd4Name', 'incomeAdd4Amount', 'incomeOtherAmount', 'summaryIncomeAmount',
            'outcomeTaxAmount', 'outcomeHomeLoanAmount', 'outcomeCoopAmount', 'outcomeEduLoanAmount', 'outcomeGPFAmount',
            'outcomeGPFAddAmount', 'outcomeGHBankAmount', 'outcomeGSBankAmount', 'outcomeUniverLoanAmount',
            'outcomeTorPorLoanAmount', 'outcomeCivilPenaltyAmount', 'outcomeRestoreAmount', 'outcomeUtilitieAmount',
            'outcomeClubAmount', 'outcomeTeacherDeadAmount', 'outcomeCoopDeadAmount',
            'outcomeAdd1Name', 'outcomeAdd1Amount', 'outcomeAdd2Name', 'outcomeAdd2Amount', 'outcomeAdd3Name', 'outcomeAdd3Amount',
            'outcomeAdd4Name', 'outcomeAdd4Amount', 'outcomeAdd5Name', 'outcomeAdd5Amount', 'outcomeAdd6Name', 'outcomeAdd6Amount',
            'outcomeAdd7Name', 'outcomeAdd7Amount', 'outcomeAdd8Name', 'outcomeAdd8Amount',
            'outcomeOtherAmount', 'summaryOutcomeAmount', 'summaryTotalAmount',
        ] as $column) {
            $table->string($column)->nullable();
        }
    });

    DB::table('person')->insert([
        ['cid' => '1111111111111', 'fname' => 'บุคลากรหน่วยหนึ่ง', 'lname' => 'ทดสอบ', 'office_id' => '01'],
        ['cid' => '2222222222222', 'fname' => 'บุคลากรหน่วยสอง', 'lname' => 'ทดสอบ', 'office_id' => '02'],
    ]);
});

function payslipUser(?string $role, ?string $office): User
{
    $user = new User;
    $user->forceFill(['id' => 7, 'name' => 'ผู้ทดสอบ', 'staff_type' => 'STAFF', 'role' => $role, 'office' => $office]);
    $user->exists = true;

    return $user;
}

function seedPayslipsInTwoOffices(): void
{
    foreach (['1111111111111', '2222222222222'] as $cid) {
        DB::table('data_salary')->insert(['cid' => $cid, 'yearNO' => '2569', 'monthNO' => '09', 'summaryTotalAmount' => '1000.00']);
    }
}

function payslipWorkbook(array $cids): UploadedFile
{
    $rows = [array_fill(0, 29, 'หัวตาราง')];
    foreach ($cids as $cid) {
        $row = array_fill(0, 29, null);
        [$row[1], $row[3], $row[7], $row[8], $row[11], $row[15], $row[26], $row[27]] = [$cid, 'ทดสอบ', '2569', '10', '1000', '1000', '0', '1000'];
        $rows[] = $row;
    }

    $spreadsheet = new Spreadsheet;
    $spreadsheet->getActiveSheet()->fromArray($rows, null, 'A1', true);
    $path = tempnam(sys_get_temp_dir(), 'payslip-import-');
    (new Xlsx($spreadsheet))->save($path);
    $file = UploadedFile::fake()->createWithContent('payslips.xlsx', file_get_contents($path));
    @unlink($path);

    return $file;
}

test('only ADMIN and FINANCE may open payslip management', function () {
    foreach (['LEAVE', 'PROMOTE', null] as $role) {
        $this->actingAs(payslipUser($role, '00'))->get(route('admin.payslips.index'))->assertForbidden();
    }

    foreach (['ADMIN', 'FINANCE'] as $role) {
        expect(\App\Support\AdminAccess::allows(payslipUser($role, '00'), 'payslip'))->toBeTrue();
    }
});

test('payslip actions are denied after the payslip role is revoked', function () {
    $component = Livewire::actingAs(payslipUser('FINANCE', '00'))->test(PayslipManagement::class);
    Auth::setUser(payslipUser('PROMOTE', '00'));

    $component->call('import')->assertForbidden();
});

test('ADMIN and FINANCE in office 00 see payslips of every office', function (string $role, ?string $office) {
    seedPayslipsInTwoOffices();

    Livewire::actingAs(payslipUser($role, $office))
        ->test(PayslipManagement::class)
        ->assertSee('บุคลากรหน่วยหนึ่ง')
        ->assertSee('บุคลากรหน่วยสอง');
})->with([
    'ADMIN without an office' => ['ADMIN', null],
    'ADMIN of another office' => ['ADMIN', '01'],
    'FINANCE of office 00' => ['FINANCE', '00'],
]);

test('FINANCE of another office sees only payslips of their own office', function () {
    seedPayslipsInTwoOffices();

    Livewire::actingAs(payslipUser('FINANCE', '01'))
        ->test(PayslipManagement::class)
        ->assertSee('บุคลากรหน่วยหนึ่ง')
        ->assertDontSee('บุคลากรหน่วยสอง')
        ->assertDontSee('2222222222222')
        ->set('search', '2222222222222')
        ->assertDontSee('บุคลากรหน่วยสอง');
});

test('FINANCE without an office sees no payslips', function () {
    seedPayslipsInTwoOffices();

    Livewire::actingAs(payslipUser('FINANCE', null))
        ->test(PayslipManagement::class)
        ->assertDontSee('บุคลากรหน่วยหนึ่ง')
        ->assertDontSee('บุคลากรหน่วยสอง');
});

test('an office-scoped import skips personnel outside the importing office', function () {
    Livewire::actingAs(payslipUser('FINANCE', '01'))
        ->test(PayslipManagement::class)
        ->set('fileUpload', payslipWorkbook(['1111111111111', '2222222222222', '3333333333333']))
        ->call('import')
        ->assertHasNoErrors()
        ->assertSet('importResult.success', 1)
        ->assertSet('importResult.skipped', 2)
        ->assertSee('ข้าม (ไม่ใช่บุคลากรในหน่วยงาน)');

    expect(DB::table('data_salary')->pluck('cid')->all())->toBe(['1111111111111']);
});

test('an unrestricted import keeps every valid row', function () {
    Livewire::actingAs(payslipUser('FINANCE', '00'))
        ->test(PayslipManagement::class)
        ->set('fileUpload', payslipWorkbook(['1111111111111', '2222222222222']))
        ->call('import')
        ->assertHasNoErrors()
        ->assertSet('importResult.success', 2)
        ->assertSet('importResult.skipped', 0);
});

test('payslip management starts with the shared admin page header', function () {
    $html = Livewire::actingAs(payslipUser('ADMIN', null))->test(PayslipManagement::class)->html();

    expect($html)->toContain('<header>')
        ->and(strpos($html, 'จัดการข้อมูลสลิปเงินเดือน'))->toBeLessThan(strpos($html, 'นำเข้าสลิปเงินเดือน'));
});

test('the payslip register can be filtered by office', function () {
    seedPayslipsInTwoOffices();
    DB::table('person')->where('office_id', '01')->update(['office_name' => 'หน่วยบริการหนึ่ง']);
    DB::table('person')->where('office_id', '02')->update(['office_name' => 'หน่วยบริการสอง']);

    Livewire::actingAs(payslipUser('FINANCE', '00'))
        ->test(PayslipManagement::class)
        ->assertSee('ทุกต้นสังกัด')
        ->assertSee('01 — หน่วยบริการหนึ่ง')
        ->assertSee('02 — หน่วยบริการสอง')
        ->set('officeFilter', '02')
        ->assertSee('บุคลากรหน่วยสอง')
        ->assertDontSee('บุคลากรหน่วยหนึ่ง');
});

test('an office-scoped user sees only their own office in the payslip office filter', function () {
    seedPayslipsInTwoOffices();

    Livewire::actingAs(payslipUser('FINANCE', '01'))
        ->test(PayslipManagement::class)
        ->assertSee('01 — ไม่ระบุชื่อต้นสังกัด')
        ->assertDontSee('02 — ไม่ระบุชื่อต้นสังกัด')
        ->set('officeFilter', '02')
        ->assertDontSee('บุคลากรหน่วยสอง');
});

test('the payslip template download is presented as a button', function () {
    Livewire::actingAs(payslipUser('FINANCE', '00'))
        ->test(PayslipManagement::class)
        ->assertSeeHtml('<a href="'.route('admin.payslips.template').'" class="btn btn-secondary shrink-0">')
        ->assertSee('ดาวน์โหลด template')
        ->assertDontSee('ดาวน์โหลดเทมเพลตที่นี่');
});
