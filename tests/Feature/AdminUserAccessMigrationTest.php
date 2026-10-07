<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    Schema::create('users', function (Blueprint $table) {
        $table->id();
        $table->string('staff_type')->nullable();
    });
});

test('migration backfills only existing administrators and leaves new provider data unrelated to access', function () {
    DB::table('users')->insert([
        ['id' => 1, 'staff_type' => 'ADMIN'],
        ['id' => 2, 'staff_type' => 'STAFF'],
    ]);

    $accessMigration = require database_path('migrations/2026_10_01_100000_add_role_and_office_to_users.php');
    $auditMigration = require database_path('migrations/2026_10_01_100100_create_user_access_audits_table.php');
    $accessMigration->up();
    $auditMigration->up();

    expect(Schema::hasColumn('users', 'role'))->toBeTrue()
        ->and(Schema::hasColumn('users', 'office'))->toBeTrue()
        ->and(Schema::hasTable('user_access_audits'))->toBeTrue()
        ->and(DB::table('users')->where('id', 1)->value('role'))->toBe('ADMIN')
        ->and(DB::table('users')->where('id', 2)->value('role'))->toBeNull();

    DB::table('users')->insert(['id' => 3, 'staff_type' => 'ADMIN']);
    expect(DB::table('users')->where('id', 3)->value('role'))->toBeNull();

    $auditMigration->down();
    $accessMigration->down();
    expect(Schema::hasColumn('users', 'role'))->toBeFalse()
        ->and(Schema::hasColumn('users', 'office'))->toBeFalse()
        ->and(Schema::hasTable('user_access_audits'))->toBeFalse();
});
