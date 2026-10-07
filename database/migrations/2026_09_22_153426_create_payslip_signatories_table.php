<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('payslip_signatories', function (Blueprint $table) {
            $table->id();
            // Thai fiscal year in Buddhist Era, e.g. 2568 for 1 Oct 2024 - 30 Sep 2025.
            // One signatory record per fiscal year; the payslip's pay period
            // decides which fiscal year (and therefore which signatory) applies.
            $table->unsignedSmallInteger('fiscal_year')->unique();
            $table->string('name');
            $table->string('position_line1');
            $table->string('position_line2')->nullable();
            $table->binary('signature_image')->nullable();
            $table->string('signature_mime')->nullable();
            $table->timestamps();
        });

        // Plain BLOB caps out at 64KB, too tight for a real signature upload.
        // LONGBLOB is only expressible via raw SQL, and only meaningful on
        // MySQL — SQLite's "blob" storage class has no such size ceiling.
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE payslip_signatories MODIFY signature_image LONGBLOB NULL');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payslip_signatories');
    }
};
