<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Personnel types (person.type_id) a PROMOTE user is responsible for on
 * /admin/salary-promotions. A PROMOTE user without rows here sees nothing.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_personnel_types', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('type_id');
            $table->primary(['user_id', 'type_id']);
        });
        Schema::table('user_access_audits', function (Blueprint $table) {
            $table->string('before_personnel_types', 20)->nullable();
            $table->string('after_personnel_types', 20)->nullable();
        });
    }

    public function down(): void
    {
        if (DB::table('user_access_audits')->whereNotNull('after_personnel_types')->exists()) {
            throw new RuntimeException('Cannot discard personnel type audit records.');
        }
        Schema::table('user_access_audits', fn (Blueprint $table) => $table->dropColumn(['before_personnel_types', 'after_personnel_types']));
        Schema::dropIfExists('user_personnel_types');
    }
};
