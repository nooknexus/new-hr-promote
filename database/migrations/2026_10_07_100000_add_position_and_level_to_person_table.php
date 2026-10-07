<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // person is shared with ProviderID and may already carry these columns.
    public function up(): void
    {
        Schema::table('person', function (Blueprint $table) {
            if (! Schema::hasColumn('person', 'position')) {
                $table->string('position', 255)->nullable();
            }
            if (! Schema::hasColumn('person', 'position_level')) {
                $table->string('position_level', 255)->nullable();
            }
        });
    }

    public function down(): void
    {
        if (DB::table('person')->whereNotNull('position')->orWhereNotNull('position_level')->exists()) {
            throw new RuntimeException('Cannot discard personnel positions and levels.');
        }

        Schema::table('person', function (Blueprint $table) {
            $table->dropColumn(['position', 'position_level']);
        });
    }
};
