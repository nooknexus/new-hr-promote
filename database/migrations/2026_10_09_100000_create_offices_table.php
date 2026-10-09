<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Office (ต้นสังกัด) registry. Offices used to exist only as distinct
 * person.office_id values, so a new office could not be chosen until someone
 * already belonged to it. offices is now the source of truth; person keeps
 * office_id and a synced copy of office_name.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('offices')) {
            Schema::create('offices', function (Blueprint $table) {
                $table->string('code', 2)->primary();
                $table->string('name');
                $table->boolean('is_active')->default(true);
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }

        $now = now();

        if (Schema::hasTable('person')) {
            $rows = DB::table('person')
                ->whereNotNull('office_id')
                ->where('office_id', '!=', '')
                ->groupBy('office_id')
                ->selectRaw('office_id, MAX(office_name) AS office_name')
                ->get()
                ->map(fn (object $row): array => [
                    'code' => (string) $row->office_id,
                    'name' => trim((string) $row->office_name) ?: (string) $row->office_id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])
                ->all();

            DB::table('offices')->insertOrIgnore($rows);
        }

        if (Schema::hasTable('users') && Schema::hasColumn('users', 'office')) {
            $rows = DB::table('users')
                ->whereNotNull('office')
                ->where('office', '!=', '')
                ->distinct()
                ->pluck('office')
                ->map(fn ($code): array => [
                    'code' => (string) $code,
                    'name' => (string) $code,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])
                ->all();

            DB::table('offices')->insertOrIgnore($rows);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('offices');
    }
};
