<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Bring the legacy tables the application reads onto the same character set
 * and collation as the Laravel tables. Mixed collations stop joins on cid
 * from using indexes (see leave_requests ↔ person), so every application
 * table is kept on utf8mb4_unicode_ci.
 */
return new class extends Migration
{
    private const TABLES = ['person', 'data_promote', 'data_salary', 'data_tax'];

    public function up(): void
    {
        $this->convert('utf8mb4', 'utf8mb4_unicode_ci');
    }

    public function down(): void
    {
        $this->convert('utf8', 'utf8_general_ci');
    }

    private function convert(string $charset, string $collation): void
    {
        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        // The schema default decides the charset of tables created outside Laravel.
        DB::statement(sprintf(
            'ALTER DATABASE `%s` CHARACTER SET %s COLLATE %s',
            str_replace('`', '``', DB::getDatabaseName()),
            $charset,
            $collation,
        ));

        foreach (self::TABLES as $table) {
            if (Schema::hasTable($table)) {
                DB::statement(sprintf('ALTER TABLE `%s` CONVERT TO CHARACTER SET %s COLLATE %s', $table, $charset, $collation));
            }
        }
    }
};
