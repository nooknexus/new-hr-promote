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
        // ตรวจสอบว่ามีคอลัมน์ pid และไม่มี thaid_pid
        if (Schema::hasColumn('users', 'pid') && !Schema::hasColumn('users', 'thaid_pid')) {
            Schema::table('users', function (Blueprint $table) {
                // เพิ่มคอลัมน์ thaid_pid
                $table->string('thaid_pid')->nullable()->unique()->after('email');
                
                // คัดลอกข้อมูลจาก pid ไปยัง thaid_pid (ถ้ามี)
                DB::statement('UPDATE users SET thaid_pid = pid WHERE pid IS NOT NULL');
                
                // ลบคอลัมน์ pid
                $table->dropColumn('pid');
            });
        } elseif (!Schema::hasColumn('users', 'thaid_pid')) {
            // ถ้าไม่มีทั้ง pid และ thaid_pid ให้สร้าง thaid_pid ใหม่
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
