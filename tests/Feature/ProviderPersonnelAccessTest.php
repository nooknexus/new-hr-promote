<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
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
        $table->boolean('is_active')->default(true);
        $table->string('hcode')->nullable();
        $table->string('hname')->nullable();
        $table->timestamp('last_login_at')->nullable();
        $table->rememberToken();
        $table->timestamps();
    });

    config()->set('services.provider.healthid_client_id', 'health-client');
    config()->set('services.provider.healthid_secret_key', 'health-secret');
    config()->set('services.provider.provider_client_id', 'provider-client');
    config()->set('services.provider.provider_secret_key', 'provider-secret');
});

function providerHealthJwt(string $cid): string
{
    $encode = static fn (array $value): string => rtrim(strtr(base64_encode(json_encode($value, JSON_THROW_ON_ERROR)), '+/', '-_'), '=');

    return $encode(['alg' => 'none', 'typ' => 'JWT']).'.'.$encode([
        'scopes_detail' => ['id_card' => $cid],
    ]).'.signature';
}

function fakeProviderLogin(string $cid): void
{
    Http::fake([
        'https://moph.id.th/api/v1/token' => Http::response([
            'data' => ['data' => ['access_token' => providerHealthJwt($cid)]],
        ], 200),
        'https://provider.id.th/api/v1/services/token' => Http::response([
            'data' => ['access_token' => 'provider-token'],
        ], 200),
        'https://provider.id.th/api/v1/services/profile' => Http::response([
            'data' => [
                'hash_cid' => hash('sha256', $cid),
                'provider_id' => 'provider-1',
                'account_id' => 'account-1',
                'title_th' => 'นาย',
                'name_th' => 'สมชาย ใจดี',
                'name_eng' => 'Mr Somchai Jaidee',
                'email' => 'somchai@example.test',
                'organization' => [[
                    'hcode' => '00051',
                    'hname_th' => 'สำนักงานสาธารณสุขจังหวัดพิษณุโลก',
                ]],
            ],
        ], 200),
    ]);
}

test('ProviderID decodes HealthID id_card and provisions a matching personnel user', function () {
    DB::table('person')->insert([
        'cid' => '1234567890123', 'pname' => 'นาย', 'fname' => 'สมชาย', 'lname' => 'ใจดี',
    ]);
    fakeProviderLogin('1234567890123');

    $this->withSession(['provider_oauth_state' => 'valid-state'])
        ->get('/provider/callback?state=valid-state&code=oauth-code')
        ->assertRedirect(route('home'));

    Http::assertSent(function ($request) {
        return $request->url() === 'https://moph.id.th/api/v1/token'
            && $request->hasHeader('Content-Type', 'application/json')
            && $request['code'] === 'oauth-code';
    });

    expect(DB::table('users')->where('pid', '1234567890123')->exists())->toBeTrue()
        ->and(DB::table('users')->where('pid', '1234567890123')->value('provider_id'))->toBe('provider-1');
});

test('ProviderID denies a valid SSO identity that is absent from personnel', function () {
    fakeProviderLogin('1234567890123');

    $this->withSession(['provider_oauth_state' => 'valid-state'])
        ->get('/provider/callback?state=valid-state&code=oauth-code')
        ->assertRedirect(route('login'));

    expect(DB::table('users')->count())->toBe(0);
});

test('ProviderID token exchange sends the configured redirect URI', function () {
    config()->set('services.provider.redirect_uri', 'https://hr.example.go.th/provider/callback');
    fakeProviderLogin('1234567890123');

    $this->withSession(['provider_oauth_state' => 'valid-state'])
        ->get('/provider/callback?state=valid-state&code=oauth-code');

    Http::assertSent(fn ($request) => $request->url() === 'https://moph.id.th/api/v1/token'
        && $request['redirect_uri'] === 'https://hr.example.go.th/provider/callback');
});
