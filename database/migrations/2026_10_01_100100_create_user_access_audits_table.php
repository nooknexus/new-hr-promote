<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_access_audits', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('actor_id');
            $table->unsignedBigInteger('target_user_id');
            $table->string('before_role', 20)->nullable();
            $table->string('after_role', 20)->nullable();
            $table->string('before_office', 2)->nullable();
            $table->string('after_office', 2)->nullable();
            $table->timestamp('created_at');
            $table->index(['target_user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        if (DB::table('user_access_audits')->exists()) {
            throw new RuntimeException('Cannot discard user access audit records.');
        }

        Schema::dropIfExists('user_access_audits');
    }
};
