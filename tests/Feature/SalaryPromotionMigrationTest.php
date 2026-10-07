<?php

use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    Schema::create('data_promote', function (Blueprint $table) {
        $table->increments('id');
        $table->string('CID');
        $table->date('evaluation_date');
    });
});

function promotionMigration(): object
{
    return require database_path('migrations/2026_09_30_100000_add_management_to_data_promote.php');
}

test('migration preserves existing promotion rows and prevents duplicate identity dates', function () {
    DB::table('data_promote')->insert(['CID' => '1234567890123', 'evaluation_date' => '2025-10-01']);
    $migration = promotionMigration();
    $migration->up();

    expect(Schema::hasColumn('data_promote', 'deleted_at'))->toBeTrue()
        ->and(Schema::hasTable('data_promote_audits'))->toBeTrue()
        ->and(DB::table('data_promote')->whereNull('deleted_at')->count())->toBe(1);
    expect(fn () => DB::table('data_promote')->insert([
        'CID' => '1234567890123', 'evaluation_date' => '2025-10-01',
    ]))->toThrow(QueryException::class);

    $migration->down();
    expect(Schema::hasColumn('data_promote', 'deleted_at'))->toBeFalse();
});

test('migration refuses preexisting duplicate identity dates before changing the table', function () {
    DB::table('data_promote')->insert([
        ['CID' => '1234567890123', 'evaluation_date' => '2025-10-01'],
        ['CID' => '1234567890123', 'evaluation_date' => '2025-10-01'],
    ]);

    expect(fn () => promotionMigration()->up())->toThrow(RuntimeException::class);
    expect(Schema::hasColumn('data_promote', 'deleted_at'))->toBeFalse()
        ->and(Schema::hasTable('data_promote_audits'))->toBeFalse();
});

test('rollback refuses to discard management audit records', function () {
    $migration = promotionMigration();
    $migration->up();
    DB::table('data_promote_audits')->insert([
        'promotion_id' => 1, 'actor_id' => 9, 'action' => 'created', 'created_at' => now(),
    ]);

    expect(fn () => $migration->down())->toThrow(RuntimeException::class);
    expect(Schema::hasTable('data_promote_audits'))->toBeTrue();
});
