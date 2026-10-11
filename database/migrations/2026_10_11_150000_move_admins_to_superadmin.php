<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * SUPERADMIN takes over everything ADMIN could do; ADMIN now only manages
 * offices, users and signatories. Existing administrators keep their access
 * by becoming SUPERADMIN.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')->where('role', 'ADMIN')->update(['role' => 'SUPERADMIN']);
    }

    public function down(): void
    {
        // Rolling back would give every new, limited ADMIN full access again.
        if (DB::table('users')->where('role', 'ADMIN')->exists()) {
            throw new RuntimeException('Cannot roll back while limited ADMIN accounts exist; change their roles first.');
        }
        DB::table('users')->where('role', 'SUPERADMIN')->update(['role' => 'ADMIN']);
    }
};
