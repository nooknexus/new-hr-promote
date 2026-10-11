<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A person can receive one withholding-tax certificate per payer per year:
 * the Comptroller General's Department (cgd, imported TXT) and the provincial
 * health office (pho, issued from monthly compensation). The payer becomes
 * part of the key, and the certificate number column is widened for
 * PHO{year}-{seq} numbers.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('data_tax', function (Blueprint $table) {
            $table->string('source', 3)->default('cgd')->after('income_year');
        });
        Schema::table('data_tax', function (Blueprint $table) {
            $table->dropPrimary();
            $table->primary(['cid', 'income_year', 'source']);
            $table->string('running_code1', 20)->nullable()->change();
        });
    }

    public function down(): void
    {
        if (DB::table('data_tax')->where('source', '!=', 'cgd')->exists()) {
            throw new RuntimeException('Cannot remove data_tax.source while provincial health office certificates exist.');
        }
        Schema::table('data_tax', function (Blueprint $table) {
            $table->dropPrimary();
            $table->primary(['cid', 'income_year']);
        });
        Schema::table('data_tax', function (Blueprint $table) {
            $table->dropColumn('source');
        });
    }
};
