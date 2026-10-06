<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_active')->default(true);
        });
        if (Schema::hasColumn('users', 'account_id')) {
            // Provider account IDs are retired; do not convert their values to status.
            Schema::table('users', fn (Blueprint $table) => $table->dropColumn('account_id'));
        }
        Schema::table('user_access_audits', function (Blueprint $table) {
            $table->boolean('before_active')->nullable();
            $table->boolean('after_active')->nullable();
        });
    }

    public function down(): void
    {
        if (DB::table('users')->where('is_active', false)->exists()) {
            throw new RuntimeException('Cannot remove account status while disabled accounts exist.');
        }
        if (DB::table('user_access_audits')->whereNotNull('before_active')->exists()) {
            throw new RuntimeException('Cannot discard account status audit records.');
        }
        Schema::table('user_access_audits', fn (Blueprint $table) => $table->dropColumn(['before_active', 'after_active']));
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('is_active'));
        // Retired remote IDs cannot be restored; rollback recreates an empty column.
        Schema::table('users', fn (Blueprint $table) => $table->string('account_id')->nullable());
    }
};
