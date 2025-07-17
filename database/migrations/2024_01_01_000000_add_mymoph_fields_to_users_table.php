<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            // Remove mymoph_id as it's not being used
            if (!Schema::hasColumn('users', 'hospcode')) {
                $table->string('hospcode', 10)->nullable()->after('email')->comment('รหัสหน่วยงาน');
            }
            if (!Schema::hasColumn('users', 'staff_type')) {
                $table->string('staff_type', 2)->nullable()->after('hospcode')->comment('ประเภทบุคลากร');
            }
            if (!Schema::hasColumn('users', 'last_login_at')) {
                $table->timestamp('last_login_at')->nullable()->after('updated_at')->comment('เข้าสู่ระบบครั้งล่าสุด');
            }
        });
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['hospcode', 'staff_type', 'last_login_at']);
        });
    }
};
