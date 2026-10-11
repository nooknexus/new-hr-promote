<?php

use App\Livewire\Login;
use App\Models\User;
use App\Services\SsoUserProvisioner;
use App\Services\ThaidIdTokenVerifier;
use App\Services\UserAccessService;
use App\Support\AdminAccess;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function () {
    Schema::create('users', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('name_en')->nullable();
        $table->string('email')->unique();
        $table->string('password');
        $table->string('pid', 13)->nullable()->unique();
        $table->string('thaid_pid', 13)->nullable()->unique();
        $table->string('cid_hash')->nullable()->unique();
        $table->string('provider_id')->nullable()->unique();
        $table->string('role')->nullable();
        $table->string('office')->nullable();
        $table->boolean('is_active')->default(true);
        $table->string('hcode')->nullable();
        $table->string('hname')->nullable();
        $table->string('hospcode')->nullable();
        $table->text('address')->nullable();
        $table->date('birthdate')->nullable();
        $table->timestamp('email_verified_at')->nullable();
        $table->timestamp('last_login_at')->nullable();
        $table->rememberToken();
        $table->timestamps();
    });
    Schema::create('person', function (Blueprint $table) {
        $table->string('cid', 13)->primary();
        $table->string('office_id')->nullable();
        $table->string('office_name')->nullable();
    });
    Schema::create('user_access_audits', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('actor_id');
        $table->unsignedBigInteger('target_user_id');
        foreach (['before_role', 'after_role', 'before_office', 'after_office'] as $field) {
            $table->string($field)->nullable();
        }
        $table->boolean('before_active')->nullable();
        $table->boolean('after_active')->nullable();
        $table->timestamp('created_at');
    });
    User::forceCreate(['id' => 1, 'name' => 'Admin', 'email' => 'admin@example.test', 'password' => 'password123', 'role' => 'SUPERADMIN']);
    User::forceCreate(['id' => 2, 'name' => 'Local Name', 'email' => 'person@example.test', 'password' => 'password123', 'pid' => '1234567890123', 'role' => 'LEAVE', 'office' => '01', 'remember_token' => 'remember-value']);
    DB::table('person')->insert(['cid' => '1234567890123', 'office_id' => '01', 'office_name' => 'Office']);
});

test('SSO reads account status before personnel and locks personnel before refreshing the account', function () {
    $tables = [];
    DB::listen(function ($query) use (&$tables) {
        if (str_starts_with($query->sql, 'select')) {
            if (str_contains($query->sql, 'from "users"')) $tables[] = 'users';
            if (str_contains($query->sql, 'from "person"')) $tables[] = 'person';
        }
    });
    expect(app(SsoUserProvisioner::class)->provision('1234567890123', ['name' => 'Refreshed']))->not->toBeNull();
    expect(array_slice($tables, 0, 3))->toBe(['users', 'person', 'users']);
});

test('SSO rechecks account status if suspension occurs after the preliminary read', function () {
    $suspended = false;
    DB::listen(function ($query) use (&$suspended) {
        if (! $suspended && str_starts_with($query->sql, 'select') && str_contains($query->sql, 'from "users"')) {
            $suspended = true;
            DB::table('users')->where('id', 2)->update(['is_active' => false]);
        }
    });
    expect(app(SsoUserProvisioner::class)->provision('1234567890123', ['name' => 'Remote name']))->toBeNull();
    expect(User::find(2)->name)->toBe('Local Name')->and(User::find(2)->is_active)->toBeFalse();
});

test('disabling and reenabling an account is audited and does not alter its identity or role', function () {
    $service = app(UserAccessService::class);
    $service->setActive(1, 2, false);
    $user = User::find(2);
    expect($user->is_active)->toBeFalse()->and($user->pid)->toBe('1234567890123')
        ->and($user->role)->toBe('LEAVE')->and($user->office)->toBe('01')->and($user->remember_token)->toBeNull();
    $audit = DB::table('user_access_audits')->first();
    expect((bool) $audit->before_active)->toBeTrue()->and((bool) $audit->after_active)->toBeFalse();
    $service->setActive(1, 2, false);
    expect(DB::table('user_access_audits')->count())->toBe(1);
    $service->setActive(1, 2, true);
    expect(User::find(2)->is_active)->toBeTrue()->and(DB::table('user_access_audits')->count())->toBe(2);
});

test('an inactive admin and a nonadmin cannot change access or account status', function () {
    DB::table('users')->where('id', 1)->update(['is_active' => false]);
    foreach ([1, 2] as $actor) {
        try { app(UserAccessService::class)->setActive($actor, 2, false); $this->fail('Unauthorized actor allowed'); }
        catch (\Symfony\Component\HttpKernel\Exception\HttpException $error) { expect($error->getStatusCode())->toBe(403); }
    }
    $this->actingAs(User::find(1));
    expect(AdminAccess::allows(User::find(1), 'users'))->toBeFalse();
});

test('self disable is denied and disabled admins do not count as remaining administrators', function () {
    User::forceCreate(['id' => 3, 'name' => 'Inactive admin', 'email' => 'inactive@example.test', 'password' => 'password123', 'role' => 'SUPERADMIN', 'is_active' => false]);
    $service = app(UserAccessService::class);
    expect(fn () => $service->setActive(1, 1, false))->toThrow(ValidationException::class)
        ->and(fn () => $service->update(1, 1, 'LEAVE', null))->toThrow(ValidationException::class);
    expect(User::find(1)->is_active)->toBeTrue()->and(User::find(1)->role)->toBe('SUPERADMIN');
});

test('a disabled CID is rejected before personnel lookup and without refresh writes', function (string $link) {
    DB::table('users')->where('id', 2)->update(['pid' => null, $link => $link === 'cid_hash' ? hash('sha256', '1234567890123') : '1234567890123', 'is_active' => false]);
    $queries = [];
    DB::listen(function ($query) use (&$queries) { $queries[] = $query->sql; });
    $result = app(SsoUserProvisioner::class)->provision('1234567890123', ['name' => 'Remote name']);
    expect($result)->toBeNull()->and(User::find(2)->name)->toBe('Local Name');
    expect(collect($queries)->contains(fn ($sql) => str_contains($sql, '"person"')))->toBeFalse();
})->with(['pid', 'thaid_pid', 'cid_hash']);

test('disabled users cannot use password login or existing sessions', function () {
    DB::table('users')->where('id', 2)->update(['is_active' => false]);
    Livewire::test(Login::class)->set('email', 'person@example.test')->set('password', 'password123')
        ->call('login')->assertDispatched('login-error');
    $this->assertGuest();
    $this->actingAs(User::find(2))->get('/profile')->assertRedirect(route('login'));
    $this->assertGuest();
});

test('disabling deletes the target database sessions without deleting anyone elses sessions', function () {
    Schema::create('sessions', function (Blueprint $table) { $table->string('id')->primary(); $table->unsignedBigInteger('user_id')->nullable(); });
    DB::table('sessions')->insert([['id' => 'target', 'user_id' => 2], ['id' => 'actor', 'user_id' => 1]]);
    config()->set('session.driver', 'database');
    app(UserAccessService::class)->setActive(1, 2, false);
    expect(DB::table('sessions')->pluck('id')->all())->toBe(['actor']);
});

test('disabled remembered sessions and Livewire requests cannot enter the application', function () {
    DB::table('users')->where('id', 2)->update(['is_active' => false]);
    $user = User::find(2);
    $this->withCookie(Auth::guard()->getRecallerName(), $user->id.'|remember-value|'.$user->password)
        ->get('/profile')->assertRedirect(route('login'));
    $this->assertGuest();
    $this->actingAs($user)->postJson('/livewire/update', ['components' => []], ['X-Livewire' => 'true'])
        ->assertForbidden();
    $this->assertGuest();
});

test('the legacy ThaiD registration cannot create another account for a disabled CID', function () {
    DB::table('users')->where('id', 2)->update(['is_active' => false]);
    session(['thaid_pid' => '1234567890123', 'thaid_name' => 'Remote name']);
    Livewire::test(Login::class)->set('email', 'another@example.test')->set('password', 'password123')
        ->call('registerWithThaID')->assertHasErrors('general');
    $this->assertGuest();
    expect(DB::table('users')->count())->toBe(2)->and(User::find(2)->name)->toBe('Local Name');
});

test('reenabled accounts can use their original password without losing their assigned role', function () {
    app(UserAccessService::class)->setActive(1, 2, false);
    app(UserAccessService::class)->setActive(1, 2, true);
    Livewire::test(Login::class)->set('email', 'person@example.test')->set('password', 'password123')
        ->call('login')->assertDispatched('login-success');
    $this->assertAuthenticatedAs(User::find(2));
    expect(User::find(2)->role)->toBe('LEAVE');
});

test('a disabled account cannot be revived by the legacy ProviderID registration action', function (string $link) {
    $value = $link === 'cid_hash' ? hash('sha256', '1234567890123') : 'linked-identity';
    DB::table('users')->where('id', 2)->update([$link => $value, 'is_active' => false]);
    session(['provider_data' => [($link === 'cid_hash' ? 'hash_cid' : $link) => $value, 'name_th' => 'Remote name']]);
    $component = Livewire::test(Login::class)->set('pid', '9876543210123')->set('password', 'password123')->call('registerWithProvider')
        ->assertHasErrors('general');
    expect($component->instance()->getErrorBag()->first('general'))->toBe('บัญชีนี้ไม่สามารถเข้าใช้งานระบบได้');
    $this->assertGuest();
    expect(User::find(2)->pid)->toBe('1234567890123')->and(User::find(2)->name)->toBe('Local Name');
})->with(['cid_hash', 'provider_id']);

test('legacy ProviderID registration works without an account_id column', function (bool $existing) {
    $cid = '9876543210123';
    if ($existing) {
        DB::table('users')->where('id', 2)->update(['pid' => null, 'cid_hash' => hash('sha256', $cid)]);
    }
    session(['provider_data' => [
        'hash_cid' => hash('sha256', $cid), 'provider_id' => 'provider-new',
        'account_id' => 'ignored-remote-account', 'name_th' => 'Provider user',
        'email' => 'new-provider@example.test',
    ]]);
    Livewire::test(Login::class)->set('pid', $cid)->set('password', 'password123')
        ->call('registerWithProvider')->assertHasNoErrors();
    $this->assertAuthenticated();
    expect(Auth::user()->pid)->toBe($cid)
        ->and(DB::table('users')->count())->toBe($existing ? 2 : 3);
})->with([false, true]);

test('all SSO callbacks deny disabled personnel accounts without refreshing them', function (string $provider) {
    DB::table('users')->where('id', 2)->update(['is_active' => false]);
    Http::preventStrayRequests();
    if ($provider === 'thaid') {
        config()->set('services.thaid.well_known_url', 'https://thaid.test/discovery');
        cache()->forget('thaid_well_known_config');
        $this->mock(ThaidIdTokenVerifier::class, fn ($mock) => $mock->shouldReceive('verify')->once()->andReturn(['pid' => '1234567890123', 'name' => 'Remote name']));
        Http::fake(['https://thaid.test/discovery' => Http::response(['token_endpoint' => 'https://thaid.test/token', 'jwks_uri' => 'https://thaid.test/jwks']), 'https://thaid.test/token' => Http::response(['id_token' => 'synthetic']), 'https://thaid.test/jwks' => Http::response(['keys' => []])]);
        $state = ['thaid_state' => 'state', 'thaid_nonce' => 'nonce'];
    } elseif ($provider === 'mymoph') {
        config()->set('services.mymoph.token_url', 'https://mymoph.test/token');
        config()->set('services.mymoph.userinfo_url', 'https://mymoph.test/userinfo');
        Http::fake(['https://mymoph.test/token' => Http::response(['access_token' => 'synthetic']), 'https://mymoph.test/userinfo' => Http::response(['cid' => '1234567890123', 'fname' => 'Remote', 'lname' => 'name'])]);
        $state = ['mymoph_state' => 'state'];
    } else {
        $encode = fn (array $data) => rtrim(strtr(base64_encode(json_encode($data)), '+/', '-_'), '=');
        $jwt = $encode(['alg' => 'none']).'.'.$encode(['scopes_detail' => ['id_card' => '1234567890123']]).'.signature';
        Http::fake(['https://moph.id.th/api/v1/token' => Http::response(['data' => ['data' => ['access_token' => $jwt]]]), 'https://provider.id.th/api/v1/services/token' => Http::response(['data' => ['access_token' => 'synthetic']]), 'https://provider.id.th/api/v1/services/profile' => Http::response(['data' => ['provider_id' => 'provider-1', 'name_th' => 'Remote name']])]);
        $state = ['provider_oauth_state' => 'state'];
    }
    $this->withSession($state)->get('/'.$provider.'/callback?state=state&code=code')->assertRedirect(route('login'));
    $this->assertGuest();
    expect(User::find(2)->name)->toBe('Local Name')->and(User::find(2)->is_active)->toBeFalse()->and(DB::table('users')->count())->toBe(2);
})->with(['thaid', 'provider', 'mymoph']);
