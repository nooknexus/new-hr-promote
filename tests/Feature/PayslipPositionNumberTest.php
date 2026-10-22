<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    Schema::create('person', function ($table) {
        $table->string('cid', 13)->primary();
        $table->string('fname')->nullable();
        $table->string('lname')->nullable();
        $table->string('type_name')->nullable();
        $table->string('office_name')->nullable();
        $table->string('no_position')->nullable();
    });
    Schema::create('payslip_signatories', function ($table) {
        $table->id();
        $table->unsignedSmallInteger('fiscal_year')->unique();
        $table->string('name');
        $table->string('position_line1');
        $table->string('position_line2')->nullable();
        $table->binary('signature_image')->nullable();
        $table->string('signature_mime')->nullable();
        $table->timestamps();
    });
    Schema::create('users', function ($table) {
        $table->id();
        $table->string('name')->nullable();
        $table->string('pid', 13)->nullable();
        $table->string('no_position')->nullable();
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

    DB::table('data_salary')->insert(['cid' => '1234567890123', 'yearNO' => '2569', 'monthNO' => '09', 'summaryTotalAmount' => '1000.00']);
});

function payslipOwner(): User
{
    $user = new User;
    $user->forceFill(['id' => 1, 'name' => 'ผู้รับเงินเดือน', 'pid' => '1234567890123']);
    $user->exists = true;

    return $user;
}

function payslipPositionNumber(?string $personNumber, ?string $userNumber): string
{
    DB::table('person')->insert(['cid' => '1234567890123', 'fname' => 'ทดสอบ', 'lname' => 'สลิป', 'no_position' => $personNumber]);
    DB::table('users')->insert(['id' => 1, 'pid' => '1234567890123', 'no_position' => $userNumber]);

    $html = test()->actingAs(payslipOwner())->get(route('payslip.print-view', '256909'))->assertOk()->getContent();
    preg_match('/เลขที่ตำแหน่ง:<\/strong>\s*([^<]*)</u', $html, $match);

    return trim($match[1] ?? '');
}

test('the payslip shows the position number maintained on the personnel record', function () {
    expect(payslipPositionNumber('1005', null))->toBe('1005');
});

test('the personnel position number wins over an outdated account copy', function () {
    expect(payslipPositionNumber('1005', '1001'))->toBe('1005');
});

test('the account position number is a fallback when the personnel record has none', function () {
    expect(payslipPositionNumber(null, '1001'))->toBe('1001');
});
