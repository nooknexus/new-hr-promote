<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('data_promote')
            ->select('CID', 'evaluation_date')
            ->groupBy('CID', 'evaluation_date')
            ->havingRaw('COUNT(*) > 1')
            ->exists()) {
            throw new RuntimeException('Cannot add salary promotion uniqueness: duplicate CID and evaluation date rows exist.');
        }

        Schema::table('data_promote', function (Blueprint $table) {
            $table->dateTime('deleted_at')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->index(['CID', 'deleted_at', 'evaluation_date'], 'data_promote_active_history_idx');
            $table->unique(['CID', 'evaluation_date'], 'data_promote_person_date_unique');
        });

        Schema::create('data_promote_audits', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('promotion_id')->index();
            $table->unsignedBigInteger('actor_id');
            $table->string('action', 20);
            $table->json('before_data')->nullable();
            $table->json('after_data')->nullable();
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        if (DB::table('data_promote_audits')->exists() || DB::table('data_promote')->whereNotNull('deleted_at')->exists()) {
            throw new RuntimeException('Cannot roll back salary promotion management while audit or archived records exist.');
        }

        Schema::dropIfExists('data_promote_audits');

        Schema::table('data_promote', function (Blueprint $table) {
            $table->dropUnique('data_promote_person_date_unique');
            $table->dropIndex('data_promote_active_history_idx');
            $table->dropColumn(['deleted_at', 'deleted_by']);
        });
    }
};
