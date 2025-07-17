<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('data_salary', function (Blueprint $table) {
            $table->string('yearNO', 4);
            $table->string('monthNO', 2);
            $table->string('cid', 13);
            $table->string('ministry', 80)->nullable();
            $table->string('depart', 80)->nullable();
            $table->string('bankId', 4)->nullable();
            $table->string('bankName', 50)->nullable();
            $table->string('bankBranchId', 4)->nullable();
            $table->string('bankBranchName', 50)->nullable();
            $table->string('bankNo', 15)->nullable();
            $table->string('incomeSalaryAmount', 12)->nullable();
            $table->string('incomeSalaryBackPayAmount', 12)->nullable();
            $table->string('incomeManagerMoneyAmount', 12)->nullable();
            $table->string('incomeManagerMoneyBackPayAmount', 12)->nullable();
            $table->string('incomePositionKingAmount', 12)->nullable();
            $table->string('incomeWarAmount', 12)->nullable();
            $table->string('incomeCourseFeeAmount', 12)->nullable();
            $table->string('incomeBreakLawAmount', 12)->nullable();
            $table->string('incomeSpecialPlaceAmount', 12)->nullable();
            $table->string('incomeSouthAmount', 12)->nullable();
            $table->string('incomePositionMoneyAmount', 12)->nullable();
            $table->string('incomePositionMoneyBackPayAmount', 12)->nullable();
            $table->string('incomeLv8Amount', 12)->nullable();
            $table->string('incomeLv8BackPayAmount', 12)->nullable();
            $table->string('incomeLv1to7Amount', 12)->nullable();
            $table->string('incomeSpecialHightAmount', 12)->nullable();
            $table->string('incomeHouseRentAmount', 12)->nullable();
            $table->string('incomeChildFeeAmount', 12)->nullable();
            $table->string('incomeChildEducationFeeAmount', 12)->nullable();
            $table->string('incomeRewardAmount', 12)->nullable();
            $table->string('incomeAdd1Name', 40)->nullable();
            $table->string('incomeAdd1Amount', 12)->nullable();
            $table->string('incomeAdd2Name', 40)->nullable();
            $table->string('incomeAdd2Amount', 12)->nullable();
            $table->string('incomeAdd3Name', 40)->nullable();
            $table->string('incomeAdd3Amount', 12)->nullable();
            $table->string('incomeAdd4Name', 40)->nullable();
            $table->string('incomeAdd4Amount', 12)->nullable();
            $table->string('incomeOtherAmount', 12)->nullable();
            $table->string('summaryIncomeAmount', 12)->nullable();
            $table->string('outcomeTaxAmount', 12)->nullable();
            $table->string('outcomeHomeLoanAmount', 12)->nullable();
            $table->string('outcomeCoopAmount', 12)->nullable();
            $table->string('outcomeEduLoanAmount', 12)->nullable();
            $table->string('outcomeGPFAmount', 12)->nullable();
            $table->string('outcomeGPFAddAmount', 12)->nullable();
            $table->string('outcomeGHBankAmount', 12)->nullable();
            $table->string('outcomeGSBankAmount', 12)->nullable();
            $table->string('outcomeUniverLoanAmount', 12)->nullable();
            $table->string('outcomeTorPorLoanAmount', 12)->nullable();
            $table->string('outcomeCivilPenaltyAmount', 12)->nullable();
            $table->string('outcomeRestoreAmount', 12)->nullable();
            $table->string('outcomeUtilitieAmount', 12)->nullable();
            $table->string('outcomeClubAmount', 12)->nullable();
            $table->string('outcomeTeacherDeadAmount', 12)->nullable();
            $table->string('outcomeCoopDeadAmount', 12)->nullable();
            $table->string('outcomeAdd1Name', 40)->nullable();
            $table->string('outcomeAdd1Amount', 12)->nullable();
            $table->string('outcomeAdd2Name', 40)->nullable();
            $table->string('outcomeAdd2Amount', 12)->nullable();
            $table->string('outcomeAdd3Name', 40)->nullable();
            $table->string('outcomeAdd3Amount', 12)->nullable();
            $table->string('outcomeAdd4Name', 40)->nullable();
            $table->string('outcomeAdd4Amount', 12)->nullable();
            $table->string('outcomeAdd5Name', 40)->nullable();
            $table->string('outcomeAdd5Amount', 12)->nullable();
            $table->string('outcomeAdd6Name', 40)->nullable();
            $table->string('outcomeAdd6Amount', 12)->nullable();
            $table->string('outcomeAdd7Name', 40)->nullable();
            $table->string('outcomeAdd7Amount', 12)->nullable();
            $table->string('outcomeAdd8Name', 40)->nullable();
            $table->string('outcomeAdd8Amount', 12)->nullable();
            $table->string('outcomeOtherAmount', 40)->nullable();
            $table->string('summaryOutcomeAmount', 12)->nullable();
            $table->string('summaryTotalAmount', 12)->nullable();
            $table->string('verifyDate', 15)->nullable();
            $table->datetime('r_update')->nullable();
            
            // Set primary key
            $table->primary(['yearNO', 'monthNO', 'cid']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('data_salary');
    }
};
