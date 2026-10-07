<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Older migrations created users.staff_type as varchar(2), users.hospcode as
 * varchar(10) and, on some databases, users.no_position as varchar(10). The
 * salary promotion import writes position numbers up to 255 characters, so
 * all three are widened to match the personnel columns.
 */
return new class extends Migration
{
    private const COLUMNS = ['staff_type', 'hospcode', 'no_position'];

    public function up(): void
    {
        $columns = array_values(array_filter(self::COLUMNS, fn (string $column) => Schema::hasColumn('users', $column)));

        if ($columns === []) {
            return;
        }

        Schema::table('users', function (Blueprint $table) use ($columns) {
            foreach ($columns as $column) {
                $table->string($column, 255)->nullable()->change();
            }
        });
    }

    public function down(): void
    {
        // Narrowing would truncate stored values, so the wider columns are kept.
    }
};
