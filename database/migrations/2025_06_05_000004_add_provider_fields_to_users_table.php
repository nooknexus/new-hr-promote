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
        Schema::table('users', function (Blueprint $table) {
            // Add Provider ID related fields if they don't exist
            if (!Schema::hasColumn('users', 'pid')) {
                $table->string('pid')->nullable()->unique()->after('email');
            }
            
            if (!Schema::hasColumn('users', 'provider_id')) {
                $table->string('provider_id')->nullable()->unique()->after('email');
            }
            
            if (!Schema::hasColumn('users', 'account_id')) {
                $table->string('account_id')->nullable()->unique()->after('email');
            }
            
            if (!Schema::hasColumn('users', 'cid_hash')) {
                $table->string('cid_hash')->nullable()->unique()->after('email');
            }
            
            if (!Schema::hasColumn('users', 'hcode')) {
                $table->string('hcode')->nullable()->after('email');
            }
            
            if (!Schema::hasColumn('users', 'hname')) {
                $table->string('hname')->nullable()->after('email');
            }
            
            // Add no_position column for storing position number
            if (!Schema::hasColumn('users', 'no_position')) {
                $table->string('no_position')->nullable()->after('hname');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['provider_id', 'account_id', 'cid_hash', 'hcode', 'hname', 'no_position']);
        });
    }
};
