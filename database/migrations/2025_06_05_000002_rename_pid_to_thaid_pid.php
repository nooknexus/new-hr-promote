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
        // Keep users.pid: the application reads both pid and thaid_pid. The
        // copy of existing pid values into thaid_pid happens in the next
        // migration (update_existing_pid_data), after this column exists.
        if (! Schema::hasColumn('users', 'thaid_pid')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('thaid_pid')->nullable()->unique()->after('email');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('users', 'thaid_pid') && !Schema::hasColumn('users', 'pid')) {
            Schema::table('users', function (Blueprint $table) {
                // เพิ่มคอลัมน์ pid
                $table->string('pid')->nullable()->unique()->after('email');
                
                // คัดลอกข้อมูลจาก thaid_pid ไปยัง pid
                DB::statement('UPDATE users SET pid = thaid_pid WHERE thaid_pid IS NOT NULL');
                
                // ลบคอลัมน์ thaid_pid
                $table->dropColumn('thaid_pid');
            });
        }
    }
};
