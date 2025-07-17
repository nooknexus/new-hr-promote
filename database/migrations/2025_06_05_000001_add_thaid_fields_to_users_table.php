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
            if (!Schema::hasColumn('users', 'pid')) {
                $table->string('pid')->nullable()->unique()->after('email');
            }
            
            if (!Schema::hasColumn('users', 'name_en')) {
                $table->string('name_en')->nullable()->after('name');
            }
            
            if (!Schema::hasColumn('users', 'birthdate')) {
                $table->date('birthdate')->nullable()->after('name_en');
            }
            
            if (!Schema::hasColumn('users', 'address')) {
                $table->text('address')->nullable()->after('birthdate');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['birthdate', 'address']);
            // ไม่ drop pid และ name_en เพราะอาจมีการใช้งานอื่นๆ
        });
    }
};
