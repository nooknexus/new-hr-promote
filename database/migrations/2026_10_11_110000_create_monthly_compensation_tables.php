<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Monthly compensation paid by offices (ต้นสังกัด) and the provincial health
 * office's yearly withholding-tax certificate workflow built on it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monthly_compensations', function (Blueprint $table) {
            $table->id();
            $table->string('cid', 13);
            $table->unsignedSmallInteger('income_year');
            $table->unsignedTinyInteger('month');
            $table->string('office_id', 2);
            foreach (['salary', 'pts', 'non_practice', 'kts', 'back_pay', 'other_amount', 'withholding_tax', 'social_security', 'provident_fund'] as $amount) {
                $table->decimal($amount, 12, 2)->default(0);
            }
            $table->string('other_name')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->unique(['cid', 'income_year', 'month']);
            $table->index(['income_year', 'office_id']);
        });

        Schema::create('compensation_year_confirmations', function (Blueprint $table) {
            $table->unsignedSmallInteger('income_year');
            $table->string('office_id', 2);
            $table->unsignedBigInteger('confirmed_by');
            $table->timestamp('confirmed_at');
            $table->primary(['income_year', 'office_id']);
        });

        Schema::create('compensation_years', function (Blueprint $table) {
            $table->unsignedSmallInteger('income_year')->primary();
            $table->boolean('locked')->default(false);
            $table->string('date_report', 8)->nullable();
            $table->unsignedBigInteger('issued_by')->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->timestamps();
        });

        Schema::create('tax_payer_settings', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50);
            $table->string('tax_id', 15);
            $table->string('address');
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
        });

        Schema::create('compensation_audits', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('actor_id');
            $table->string('action', 30);
            $table->unsignedSmallInteger('income_year');
            $table->unsignedTinyInteger('month')->nullable();
            $table->string('office_id', 2)->nullable();
            $table->unsignedBigInteger('compensation_id')->nullable();
            $table->unsignedInteger('row_count')->nullable();
            $table->timestamp('created_at');
            $table->index(['income_year', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compensation_audits');
        Schema::dropIfExists('tax_payer_settings');
        Schema::dropIfExists('compensation_years');
        Schema::dropIfExists('compensation_year_confirmations');
        Schema::dropIfExists('monthly_compensations');
    }
};
