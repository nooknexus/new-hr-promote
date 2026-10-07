<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates data_salary in the shape the application uses (an id for payslip
 * downloads, decimal amounts, one row per cid/year/month). Databases that
 * already hold the legacy table are left untouched.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('data_salary')) {
            return;
        }

        Schema::create('data_salary', function (Blueprint $table) {
                $table->increments('id');
                $table->string('yearNO', 10)->nullable();
                $table->string('monthNO', 10)->nullable();
                $table->string('cid', 20)->nullable();
                $table->string('pname', 50)->nullable();
                $table->string('fname', 100)->nullable();
                $table->string('lname', 100)->nullable();
                $table->string('ministry', 100)->nullable();
                $table->string('depart', 100)->nullable();
                $table->string('bankId', 10)->nullable();
                $table->string('bankName', 100)->nullable();
                $table->string('bankBranchId', 10)->nullable();
                $table->string('bankBranchName', 100)->nullable();
                $table->string('bankNo', 20)->nullable();
                $table->decimal('incomeSalaryAmount', 10, 2)->nullable();
                $table->decimal('incomeSalaryBackPayAmount', 10, 2)->nullable();
                $table->decimal('incomeManagerMoneyAmount', 10, 2)->nullable();
                $table->decimal('incomeManagerMoneyBackPayAmount', 10, 2)->nullable();
                $table->decimal('incomePositionKingAmount', 10, 2)->nullable();
                $table->decimal('incomeWarAmount', 10, 2)->nullable();
                $table->decimal('incomeCourseFeeAmount', 10, 2)->nullable();
                $table->decimal('incomeBreakLawAmount', 10, 2)->nullable();
                $table->decimal('incomeSpecialPlaceAmount', 10, 2)->nullable();
                $table->decimal('incomeSouthAmount', 10, 2)->nullable();
                $table->decimal('incomePositionMoneyAmount', 10, 2)->nullable();
                $table->decimal('incomePositionMoneyBackPayAmount', 10, 2)->nullable();
                $table->decimal('incomeLv8Amount', 10, 2)->nullable();
                $table->decimal('incomeLv8BackPayAmount', 10, 2)->nullable();
                $table->decimal('incomeLv1to7Amount', 10, 2)->nullable();
                $table->decimal('incomeSpecialHightAmount', 10, 2)->nullable();
                $table->decimal('incomeHouseRentAmount', 10, 2)->nullable();
                $table->decimal('incomeChildFeeAmount', 10, 2)->nullable();
                $table->decimal('incomeChildEducationFeeAmount', 10, 2)->nullable();
                $table->decimal('incomeRewardAmount', 10, 2)->nullable();
                $table->string('incomeAdd1Name', 100)->nullable();
                $table->decimal('incomeAdd1Amount', 10, 2)->nullable();
                $table->string('incomeAdd2Name', 100)->nullable();
                $table->decimal('incomeAdd2Amount', 10, 2)->nullable();
                $table->string('incomeAdd3Name', 100)->nullable();
                $table->decimal('incomeAdd3Amount', 10, 2)->nullable();
                $table->string('incomeAdd4Name', 100)->nullable();
                $table->decimal('incomeAdd4Amount', 10, 2)->nullable();
                $table->decimal('incomeOtherAmount', 10, 2)->nullable();
                $table->decimal('summaryIncomeAmount', 10, 2)->nullable();
                $table->decimal('outcomeTaxAmount', 10, 2)->nullable();
                $table->decimal('outcomeHomeLoanAmount', 10, 2)->nullable();
                $table->decimal('outcomeCoopAmount', 10, 2)->nullable();
                $table->decimal('outcomeEduLoanAmount', 10, 2)->nullable();
                $table->decimal('outcomeGPFAmount', 10, 2)->nullable();
                $table->decimal('outcomeGPFAddAmount', 10, 2)->nullable();
                $table->decimal('outcomeGHBankAmount', 10, 2)->nullable();
                $table->decimal('outcomeGSBankAmount', 10, 2)->nullable();
                $table->decimal('outcomeUniverLoanAmount', 10, 2)->nullable();
                $table->decimal('outcomeTorPorLoanAmount', 10, 2)->nullable();
                $table->decimal('outcomeCivilPenaltyAmount', 10, 2)->nullable();
                $table->decimal('outcomeRestoreAmount', 10, 2)->nullable();
                $table->decimal('outcomeUtilitieAmount', 10, 2)->nullable();
                $table->decimal('outcomeClubAmount', 10, 2)->nullable();
                $table->decimal('outcomeTeacherDeadAmount', 10, 2)->nullable();
                $table->decimal('outcomeCoopDeadAmount', 10, 2)->nullable();
                $table->string('outcomeAdd1Name', 100)->nullable();
                $table->decimal('outcomeAdd1Amount', 10, 2)->nullable();
                $table->string('outcomeAdd2Name', 100)->nullable();
                $table->decimal('outcomeAdd2Amount', 10, 2)->nullable();
                $table->string('outcomeAdd3Name', 100)->nullable();
                $table->decimal('outcomeAdd3Amount', 10, 2)->nullable();
                $table->string('outcomeAdd4Name', 100)->nullable();
                $table->decimal('outcomeAdd4Amount', 10, 2)->nullable();
                $table->string('outcomeAdd5Name', 100)->nullable();
                $table->decimal('outcomeAdd5Amount', 10, 2)->nullable();
                $table->string('outcomeAdd6Name', 100)->nullable();
                $table->decimal('outcomeAdd6Amount', 10, 2)->nullable();
                $table->string('outcomeAdd7Name', 100)->nullable();
                $table->decimal('outcomeAdd7Amount', 10, 2)->nullable();
                $table->string('outcomeAdd8Name', 100)->nullable();
                $table->decimal('outcomeAdd8Amount', 10, 2)->nullable();
                $table->decimal('outcomeOtherAmount', 10, 2)->nullable();
                $table->decimal('summaryOutcomeAmount', 10, 2)->nullable();
                $table->decimal('summaryTotalAmount', 10, 2)->nullable();
                $table->date('verifyDate')->nullable();
                $table->dateTime('r_update')->nullable();
                $table->unique(['cid', 'yearNO', 'monthNO'], 'cid');
        });
    }

    public function down(): void
    {
        // data_salary holds payslip records that may predate this migration.
    }
};
