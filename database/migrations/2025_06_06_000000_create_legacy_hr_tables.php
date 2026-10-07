<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * person, data_promote and data_tax came from the earlier ProviderID system and
 * were never created by a migration, so a fresh database could not run the
 * later migrations that alter them. Create their original shape only when
 * missing; databases that already hold these tables are left untouched.
 * Columns added by later migrations (person.employment_started_at,
 * no_position, position, position_level; data_promote.deleted_at, deleted_by
 * and its indexes) are intentionally left to those migrations.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('person')) {
            Schema::create('person', function (Blueprint $table) {
                $table->string('cid', 13)->primary();
                $table->string('pname')->nullable();
                $table->string('fname')->nullable();
                $table->string('lname')->nullable();
                $table->integer('type_id')->nullable();
                $table->string('type_name')->nullable();
                $table->string('office_id', 2)->nullable();
                $table->string('office_name')->nullable();
            });
        }

        if (! Schema::hasTable('data_promote')) {
            Schema::create('data_promote', function (Blueprint $table) {
                $table->increments('id');
                $table->string('CID');
                $table->date('evaluation_date');
                $table->string('promote', 2)->nullable();
                $table->string('ps')->nullable();
                $table->string('office')->nullable();
                $table->string('department')->nullable();
                $table->string('before_salary', 10)->nullable();
                $table->string('base', 100)->nullable();
                $table->string('percent', 10)->nullable();
                $table->string('sum', 10)->nullable();
                $table->string('extra', 10)->nullable();
                $table->string('new_salary', 10)->nullable();
                $table->dateTime('update_date')->nullable();
            });
        }

        if (! Schema::hasTable('data_tax')) {
            Schema::create('data_tax', function (Blueprint $table) {
                $table->string('running_code1', 10)->nullable();
                $table->string('department_name', 50)->nullable();
                $table->string('department_tax_id', 15)->nullable();
                $table->string('department_addr')->nullable();
                $table->string('cid', 13);
                $table->string('tax_id', 10)->nullable();
                $table->string('fullname')->nullable();
                $table->string('addr')->nullable();
                $table->string('running_code2', 10)->nullable();
                $table->string('income_type_id', 1)->nullable();
                $table->string('income_type_name', 20)->nullable();
                $table->string('income_year', 4);
                $table->string('income_money', 12)->nullable();
                $table->string('withholding_tax', 12)->nullable();
                $table->string('total_money', 12)->nullable();
                $table->string('sumTaxAmount', 12)->nullable();
                $table->string('sumTaxText')->nullable();
                $table->string('outcomeType', 2)->nullable();
                $table->string('outcomeName', 50)->nullable();
                $table->string('outcomeAmount', 12)->nullable();
                $table->string('tax_type_id', 2)->nullable();
                $table->string('tax_type_name', 50)->nullable();
                $table->string('date_report', 8)->nullable();
                $table->dateTime('r_update')->nullable();
                $table->string('socialSecurity', 40)->nullable();
                $table->primary(['cid', 'income_year']);
            });
        }
    }

    public function down(): void
    {
        // These tables hold shared personnel, promotion and tax records that may
        // predate this migration, so rolling back never drops them.
    }
};
