<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // ตรวจสอบว่ามีคอลัมน์ pid และ thaid_pid
        if (Schema::hasColumn('users', 'pid') && Schema::hasColumn('users', 'thaid_pid')) {
            // คัดลอกข้อมูลจาก pid ไปยัง thaid_pid สำหรับเรคอร์ดที่ thaid_pid เป็น null แต่ pid มีค่า
            DB::statement('UPDATE users SET thaid_pid = pid WHERE thaid_pid IS NULL AND pid IS NOT NULL');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // ไม่ต้องทำอะไรในการ rollback เพราะเป็นเพียงการอัพเดทข้อมูล
    }
};
