<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    Schema::create('users', function (Blueprint $table) { $table->id(); $table->string('name'); $table->string('account_id')->nullable(); });
    Schema::create('user_access_audits', function (Blueprint $table) { $table->id(); });
    DB::table('users')->insert(['id' => 1, 'name' => 'Existing user', 'account_id' => 'legacy-provider-account']);
});

test('account status migration enables existing users and preserves them through rollback', function () {
    $migration = require database_path('migrations/2026_10_06_150000_add_account_status_to_users.php');
    $migration->up();
    expect((bool) DB::table('users')->value('is_active'))->toBeTrue()
        ->and(Schema::hasColumn('users', 'account_id'))->toBeFalse()
        ->and(Schema::hasColumn('user_access_audits', 'after_active'))->toBeTrue();
    $migration->down();
    expect(DB::table('users')->value('name'))->toBe('Existing user')
        ->and(Schema::hasColumn('users', 'account_id'))->toBeTrue()
        ->and(Schema::hasColumn('users', 'is_active'))->toBeFalse();
});

test('rollback cannot silently reactivate a disabled user', function () {
    $migration = require database_path('migrations/2026_10_06_150000_add_account_status_to_users.php');
    $migration->up();
    DB::table('users')->where('id', 1)->update(['is_active' => false]);
    expect(fn () => $migration->down())->toThrow(RuntimeException::class);
    expect(Schema::hasColumn('users', 'is_active'))->toBeTrue();
});
