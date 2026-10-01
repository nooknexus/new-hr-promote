<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('person', function (Blueprint $table) {
            $table->string('no_position', 255)->nullable();
        });
    }

    public function down(): void
    {
        if (DB::table('person')->whereNotNull('no_position')->exists()) {
            throw new RuntimeException('Cannot discard personnel position numbers.');
        }

        Schema::table('person', function (Blueprint $table) {
            $table->dropColumn('no_position');
        });
    }
};
