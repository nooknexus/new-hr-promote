<?php

use App\Livewire\Home;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;

beforeEach(function () {
    Schema::create('data_salary', function (Blueprint $table) {
        $table->string('cid', 13);
        $table->string('yearNO')->nullable();
        $table->string('monthNO')->nullable();
        foreach ([
            'incomeSalaryAmount', 'summaryIncomeAmount', 'summaryOutcomeAmount', 'summaryTotalAmount',
            'incomeSalaryBackPayAmount', 'incomeManagerMoneyAmount', 'incomeManagerMoneyBackPayAmount',
            'incomePositionKingAmount', 'incomeWarAmount', 'incomeCourseFeeAmount', 'incomeBreakLawAmount',
            'incomeSpecialPlaceAmount', 'incomeSouthAmount', 'incomePositionMoneyAmount', 'incomePositionMoneyBackPayAmount',
            'incomeLv8Amount', 'incomeLv8BackPayAmount', 'incomeLv1to7Amount', 'incomeSpecialHightAmount',
            'incomeHouseRentAmount', 'incomeChildFeeAmount', 'incomeChildEducationFeeAmount', 'incomeRewardAmount',
            'incomeAdd1Amount', 'incomeAdd2Amount', 'incomeAdd3Amount', 'incomeAdd4Amount', 'incomeOtherAmount',
            'outcomeTaxAmount', 'outcomeHomeLoanAmount', 'outcomeCoopAmount', 'outcomeEduLoanAmount',
            'outcomeGPFAmount', 'outcomeGPFAddAmount', 'outcomeGHBankAmount', 'outcomeGSBankAmount',
            'outcomeUniverLoanAmount', 'outcomeTorPorLoanAmount', 'outcomeCivilPenaltyAmount', 'outcomeRestoreAmount',
            'outcomeUtilitieAmount', 'outcomeClubAmount', 'outcomeTeacherDeadAmount', 'outcomeCoopDeadAmount',
            'outcomeAdd1Amount', 'outcomeAdd2Amount', 'outcomeAdd3Amount', 'outcomeAdd4Amount', 'outcomeAdd5Amount',
            'outcomeAdd6Amount', 'outcomeAdd7Amount', 'outcomeAdd8Amount', 'outcomeOtherAmount',
        ] as $column) {
            $table->decimal($column, 15, 2)->nullable();
        }
    });
    Schema::create('data_tax', function (Blueprint $table) {
        $table->string('CID', 13);
        $table->decimal('total_money', 15, 2)->nullable();
        $table->decimal('withholding_tax', 15, 2)->nullable();
        $table->unsignedInteger('income_year')->nullable();
        $table->string('source', 3)->default('cgd');
    });
    Schema::create('data_promote', function (Blueprint $table) {
        $table->string('CID', 13);
        $table->date('evaluation_date');
        $table->dateTime('deleted_at')->nullable();
        $table->decimal('sum', 15, 2)->nullable();
        $table->decimal('percent', 8, 2)->nullable();
        $table->decimal('before_salary', 15, 2)->nullable();
        $table->decimal('new_salary', 15, 2)->nullable();
    });
    Schema::create('leave_types', function (Blueprint $table) {
        $table->id();
        $table->string('name', 100);
    });
    Schema::create('leave_requests', function (Blueprint $table) {
        $table->id();
        $table->string('cid', 13);
        $table->unsignedBigInteger('leave_type_id');
        $table->date('start_date');
        $table->date('end_date');
        $table->decimal('total_days', 5, 1);
        $table->string('status', 20);
    });
});

test('dashboard promotion graph ignores archived rows even when their date is latest in the round', function () {
    $user = new User;
    $user->forceFill(['id' => 1, 'name' => 'Test User', 'pid' => '1234567890123']);
    $user->exists = true;
    DB::table('data_promote')->insert([
        ['CID' => '1234567890123', 'evaluation_date' => '2026-04-01', 'sum' => 1000,
            'percent' => 3, 'before_salary' => 30000, 'new_salary' => 31000, 'deleted_at' => null],
        ['CID' => '1234567890123', 'evaluation_date' => '2026-04-15', 'sum' => 2000,
            'percent' => 6, 'before_salary' => 30000, 'new_salary' => 32000, 'deleted_at' => now()],
    ]);

    Livewire::actingAs($user)
        ->test(Home::class)
        ->assertCount('promotionHistory', 1)
        ->assertSet('promotionHistory.0.amount', 1000);
});

afterEach(function () {
    Carbon::setTestNow();
});

test('dashboard summarizes only approved leave in the current fiscal year', function () {
    Carbon::setTestNow('2026-09-28');
    $user = new User;
    $user->forceFill(['id' => 1, 'name' => 'Test User', 'pid' => '1234567890123']);
    $user->exists = true;

    $leaveTypeIds = [];
    foreach (['ลาพักผ่อน', 'ลาป่วย', 'ลากิจ'] as $leaveType) {
        $leaveTypeIds[$leaveType] = DB::table('leave_types')->insertGetId(['name' => $leaveType]);
    }
    foreach ([
        ['1234567890123', 'ลาพักผ่อน', '2025-10-01', 2.5, 'approved'],
        ['1234567890123', 'ลาป่วย', '2026-01-15', 1.0, 'approved'],
        ['1234567890123', 'ลากิจ', '2026-09-20', 3.0, 'approved'],
        ['1234567890123', 'ลาพักผ่อน', '2025-09-30', 9.0, 'approved'],
        ['1234567890123', 'ลาพักผ่อน', '2026-06-01', 7.0, 'pending'],
        ['9999999999999', 'ลาพักผ่อน', '2026-06-01', 8.0, 'approved'],
    ] as [$cid, $leaveType, $startDate, $totalDays, $status]) {
        DB::table('leave_requests')->insert([
            'cid' => $cid,
            'leave_type_id' => $leaveTypeIds[$leaveType],
            'start_date' => $startDate,
            'end_date' => $startDate,
            'total_days' => $totalDays,
            'status' => $status,
        ]);
    }

    $this->actingAs($user);

    Livewire::test(Home::class)
        ->assertSet('currentFiscalLeaveSummary.fiscal_year', 2569)
        ->assertSet('currentFiscalLeaveSummary.total_days', 6.5)
        ->assertSet('currentFiscalLeaveSummary.totals.ลาพักผ่อน', 2.5)
        ->assertSet('currentFiscalLeaveSummary.totals.ลาป่วย', 1.0)
        ->assertSet('currentFiscalLeaveSummary.totals.ลากิจ', 3.0)
        ->assertSee('ประวัติวันลาปี 2569')
        ->assertSee('รวมวันลา')
        ->assertSee('6.5')
        ->assertDontSee('รายการล่าสุด')
        ->assertDontSee('ดูทั้งหมด')
        ->assertDontSee('เฉพาะรายการที่อนุมัติแล้ว');
});
