<?php

use App\Models\User;
use App\Support\AdminAccess;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

function accessUser(?string $role, string $staffType = 'STAFF'): User
{
    $user = new User;
    $user->forceFill(['id' => 99, 'name' => 'ผู้ทดสอบ', 'role' => $role, 'staff_type' => $staffType, 'office' => '00']);
    $user->exists = true;

    return $user;
}

test('role permissions are explicit and provider staff type never grants access', function () {
    $abilities = ['users', 'personnel', 'leave', 'leave-sync', 'promotions', 'signatories'];
    foreach ($abilities as $ability) {
        expect(AdminAccess::allows(accessUser('ADMIN'), $ability))->toBeTrue();
        expect(AdminAccess::allows(accessUser(null, 'ADMIN'), $ability))->toBeFalse();
        expect(AdminAccess::allows(accessUser('UNKNOWN'), $ability))->toBeFalse();
    }

    expect(AdminAccess::allows(accessUser('LEAVE'), 'leave'))->toBeTrue()
        ->and(AdminAccess::allows(accessUser('LEAVE'), 'leave-sync'))->toBeTrue()
        ->and(AdminAccess::allows(accessUser('LEAVE'), 'promotions'))->toBeFalse()
        ->and(AdminAccess::allows(accessUser('PROMOTE'), 'promotions'))->toBeTrue()
        ->and(AdminAccess::allows(accessUser('PROMOTE'), 'personnel'))->toBeTrue()
        ->and(AdminAccess::allows(accessUser('FINANCE'), 'personnel'))->toBeTrue()
        ->and(AdminAccess::allows(accessUser('FINANCE'), 'signatories'))->toBeTrue()
        ->and(AdminAccess::allows(accessUser('FINANCE'), 'users'))->toBeFalse();
});

test('admin routes carry their own permission and a provider administrator without a role is denied', function () {
    $required = [
        'admin.personnel.index' => 'admin:personnel',
        'admin.personnel.template' => 'admin:personnel',
        'admin.leaves.index' => 'admin:leave',
        'admin.leave-sheet-sync' => 'admin:leave-sync',
        'admin.salary-promotions.index' => 'admin:promotions',
        'admin.signatories' => 'admin:signatories',
    ];
    foreach ($required as $name => $middleware) {
        expect(Route::getRoutes()->getByName($name)->gatherMiddleware())->toContain($middleware);
    }

    $this->actingAs(accessUser(null, 'ADMIN'))
        ->get('/admin/personnel/template')
        ->assertForbidden();
});

test('sidebar contains only admin links allowed by the active role', function () {
    $this->actingAs(accessUser('LEAVE'));
    $html = Blade::render('<x-layouts.app><p>เนื้อหา</p></x-layouts.app>');

    expect($html)->toContain('data-navigation-group="admin"')
        ->toContain(route('admin.leaves.index'))
        ->toContain(route('admin.personnel.index'))
        ->not->toContain(route('admin.salary-promotions.index'))
        ->not->toContain(route('admin.signatories'));
});

test('MyMOPH login preserves the locally assigned role and office', function () {
    Schema::create('person', function (Blueprint $table) {
        $table->string('cid', 13)->primary();
        $table->string('pname')->nullable();
        $table->string('fname')->nullable();
        $table->string('lname')->nullable();
    });
    Schema::create('users', function (Blueprint $table) {
        $table->id();
        $table->string('pid', 13)->nullable()->unique();
        $table->string('thaid_pid', 13)->nullable()->unique();
        $table->string('cid_hash')->nullable()->unique();
        $table->string('provider_id')->nullable()->unique();
        $table->string('account_id')->nullable()->unique();
        $table->string('name');
        $table->string('name_en')->nullable();
        $table->string('email');
        $table->timestamp('email_verified_at')->nullable();
        $table->string('password');
        $table->string('role')->nullable();
        $table->string('office')->nullable();
        $table->string('staff_type')->nullable();
        $table->string('hcode')->nullable();
        $table->string('hname')->nullable();
        $table->timestamp('last_login_at')->nullable();
        $table->timestamps();
    });
    DB::table('person')->insert(['cid' => '1234567890123']);
    DB::table('users')->insert([
        'id' => 7, 'pid' => '1234567890123', 'name' => 'เดิม', 'email' => 'old@example.test',
        'password' => 'hashed-password', 'cid_hash' => hash('sha256', '1234567890123'),
        'role' => 'PROMOTE', 'office' => '01', 'staff_type' => 'STAFF',
    ]);
    config()->set('services.mymoph.token_url', 'https://mymoph.test/token');
    config()->set('services.mymoph.userinfo_url', 'https://mymoph.test/userinfo');
    Http::fake([
        'https://mymoph.test/token' => Http::response(['access_token' => 'synthetic-token'], 200),
        'https://mymoph.test/userinfo' => Http::response([
            'cid' => '1234567890123', 'email' => 'new@example.test',
            'fname' => 'สมศรี', 'lname' => 'ใจดี', 'staff_type' => 'ADMIN',
        ], 200),
    ]);

    $this->withSession(['mymoph_state' => 'synthetic-state'])
        ->get('/mymoph/callback?state=synthetic-state&code=synthetic-code')
        ->assertRedirect(route('home'));

    expect(DB::table('users')->where('id', 7)->value('role'))->toBe('PROMOTE')
        ->and(DB::table('users')->where('id', 7)->value('office'))->toBe('01');
});

test('MyMOPH does not create a user when its CID is absent from personnel', function () {
    Schema::create('person', function (Blueprint $table) {
        $table->string('cid', 13)->primary();
    });
    Schema::create('users', function (Blueprint $table) {
        $table->id();
        $table->string('pid', 13)->nullable()->unique();
        $table->string('thaid_pid', 13)->nullable()->unique();
        $table->string('cid_hash')->nullable()->unique();
        $table->string('provider_id')->nullable()->unique();
        $table->string('account_id')->nullable()->unique();
        $table->string('name');
        $table->string('name_en')->nullable();
        $table->string('email')->unique();
        $table->timestamp('email_verified_at')->nullable();
        $table->string('password');
        $table->string('role')->nullable();
        $table->string('office')->nullable();
        $table->string('staff_type')->nullable();
        $table->string('hcode')->nullable();
        $table->string('hname')->nullable();
        $table->timestamp('last_login_at')->nullable();
        $table->timestamps();
    });
    config()->set('services.mymoph.token_url', 'https://mymoph.test/token');
    config()->set('services.mymoph.userinfo_url', 'https://mymoph.test/userinfo');
    Http::fake([
        'https://mymoph.test/token' => Http::response(['access_token' => 'synthetic-token'], 200),
        'https://mymoph.test/userinfo' => Http::response([
            'cid' => '1234567890123', 'email' => 'new@example.test',
            'fname' => 'สมศรี', 'lname' => 'ใจดี',
        ], 200),
    ]);

    $this->withSession(['mymoph_state' => 'synthetic-state'])
        ->get('/mymoph/callback?state=synthetic-state&code=synthetic-code')
        ->assertRedirect(route('login'));

    expect(DB::table('users')->count())->toBe(0);
});

test('a regular user without a role sees no admin menu even with office 00 or a provider ADMIN staff type', function (string $staffType) {
    $user = accessUser(null, $staffType);
    $this->actingAs($user);
    $html = Blade::render('<x-layouts.app><p>เนื้อหา</p></x-layouts.app>');

    expect($html)->not->toContain('data-navigation-group="admin"');
    foreach (['admin.personnel.index', 'admin.users.index', 'admin.payslips.index', 'admin.leaves.index',
        'admin.salary-promotions.index', 'admin.signatories', 'admin.tax-certificates.index'] as $route) {
        expect($html)->not->toContain(route($route));
        $this->get(route($route))->assertForbidden();
    }
})->with(['STAFF', 'ADMIN']);

test('the navigation bar shows the Thai label of the signed-in user role', function (?string $role, string $label) {
    $this->actingAs(accessUser($role));
    $html = Blade::render('<x-layouts.app><p>เนื้อหา</p></x-layouts.app>');

    expect($html)->toContain($label)->not->toContain('ผู้ใช้งานระบบ');
})->with([
    'admin' => ['ADMIN', 'ผู้ดูแลระบบ'],
    'leave' => ['LEAVE', 'เจ้าหน้าที่งานลา'],
    'promote' => ['PROMOTE', 'เจ้าหน้าที่งานเลื่อนขั้นเงินเดือน'],
    'finance' => ['FINANCE', 'เจ้าหน้าที่การเงิน'],
    'no role' => [null, 'ผู้ใช้งานทั่วไป'],
]);
