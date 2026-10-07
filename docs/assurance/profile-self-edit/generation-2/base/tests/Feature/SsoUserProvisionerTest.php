<?php

use App\Models\User;
use App\Services\SsoUserProvisioner;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    Schema::create('person', function (Blueprint $table) {
        $table->string('cid', 13)->primary();
        $table->string('pname')->nullable();
        $table->string('fname')->nullable();
        $table->string('lname')->nullable();
    });
    Schema::create('users', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('name_en')->nullable();
        $table->string('email')->unique();
        $table->timestamp('email_verified_at')->nullable();
        $table->string('password');
        $table->string('pid', 13)->nullable()->unique();
        $table->string('thaid_pid', 13)->nullable()->unique();
        $table->string('cid_hash')->nullable()->unique();
        $table->string('provider_id')->nullable()->unique();
        $table->string('account_id')->nullable()->unique();
        $table->string('hcode')->nullable();
        $table->string('hname')->nullable();
        $table->string('role')->nullable();
        $table->string('office')->nullable();
        $table->timestamp('last_login_at')->nullable();
        $table->timestamps();
    });
});

function ssoAttributes(array $overrides = []): array
{
    return [
        'name' => 'นายสมชาย ใจดี',
        'name_en' => 'Mr Somchai Jaidee',
        'email' => 'somchai@example.test',
        'provider_id' => 'provider-1',
        'account_id' => 'account-1',
        'hcode' => '00051',
        'hname' => 'สำนักงานสาธารณสุขจังหวัดพิษณุโลก',
        ...$overrides,
    ];
}

test('a trusted SSO CID matching personnel creates a linked user', function () {
    DB::table('person')->insert([
        'cid' => '1234567890123', 'pname' => 'นาย', 'fname' => 'สมชาย', 'lname' => 'ใจดี',
    ]);

    $user = app(SsoUserProvisioner::class)->provision('1234567890123', ssoAttributes());

    expect($user)->toBeInstanceOf(User::class)
        ->and($user->pid)->toBe('1234567890123')
        ->and($user->cid_hash)->toBe(hash('sha256', '1234567890123'))
        ->and($user->provider_id)->toBe('provider-1')
        ->and(DB::table('users')->count())->toBe(1);
});

test('an SSO CID absent from personnel is denied without creating a user', function () {
    $user = app(SsoUserProvisioner::class)->provision('1234567890123', ssoAttributes());

    expect($user)->toBeNull()
        ->and(DB::table('users')->count())->toBe(0);
});

test('an existing linked user is refreshed without changing local role or office', function () {
    DB::table('person')->insert(['cid' => '1234567890123']);
    DB::table('users')->insert([
        'id' => 7,
        'name' => 'ชื่อเดิม',
        'email' => 'old@example.test',
        'password' => 'hashed',
        'pid' => '1234567890123',
        'cid_hash' => hash('sha256', '1234567890123'),
        'role' => 'PROMOTE',
        'office' => '01',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $user = app(SsoUserProvisioner::class)->provision('1234567890123', ssoAttributes([
        'email' => 'new@example.test',
        'provider_id' => 'provider-1',
    ]));

    expect($user->id)->toBe(7)
        ->and(DB::table('users')->where('id', 7)->value('role'))->toBe('PROMOTE')
        ->and(DB::table('users')->where('id', 7)->value('office'))->toBe('01')
        ->and(DB::table('users')->where('id', 7)->value('email'))->toBe('new@example.test');
});

test('refreshing an existing SSO user without an email keeps the existing email address', function () {
    DB::table('person')->insert(['cid' => '1234567890123']);
    DB::table('users')->insert([
        'id' => 7,
        'name' => 'ชื่อเดิม',
        'email' => 'existing@example.test',
        'password' => 'hashed',
        'pid' => '1234567890123',
        'cid_hash' => hash('sha256', '1234567890123'),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $user = app(SsoUserProvisioner::class)->provision('1234567890123', [
        'name' => 'นาย สมชาย ใจดี',
        'provider_id' => 'provider-1',
    ]);

    expect($user?->id)->toBe(7)
        ->and($user?->email)->toBe('existing@example.test');
});

test('conflicting SSO account links do not select or log in either user', function () {
    DB::table('person')->insert(['cid' => '1234567890123']);
    DB::table('users')->insert([
        'id' => 7, 'name' => 'หนึ่ง', 'email' => 'one@example.test', 'password' => 'hashed',
        'pid' => '1234567890123', 'cid_hash' => hash('sha256', '1234567890123'),
        'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('users')->insert([
        'id' => 8, 'name' => 'สอง', 'email' => 'two@example.test', 'password' => 'hashed',
        'provider_id' => 'provider-1',
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $user = app(SsoUserProvisioner::class)->provision('1234567890123', ssoAttributes());

    expect($user)->toBeNull()
        ->and(DB::table('users')->count())->toBe(2);
});
