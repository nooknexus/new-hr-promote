<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('person') && ! Schema::hasColumn('person', 'employment_started_at')) {
            Schema::table('person', function (Blueprint $table) {
                $table->date('employment_started_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('person') && Schema::hasColumn('person', 'employment_started_at')) {
            Schema::table('person', function (Blueprint $table) {
                $table->dropColumn('employment_started_at');
            });
        }
    }
};
