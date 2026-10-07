<?php

use App\Services\ThaidIdTokenVerifier;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Mockery\MockInterface;

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
        $table->date('birthdate')->nullable();
        $table->text('address')->nullable();
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
        $table->timestamp('last_login_at')->nullable();
        $table->timestamps();
    });

    config()->set('services.thaid.well_known_url', 'https://thaid.test/.well-known');
    config()->set('services.thaid.token_endpoint', 'https://thaid.test/token');
    config()->set('services.thaid.client_id', 'thaid-client');
    config()->set('services.thaid.client_secret', 'thaid-secret');
    config()->set('services.thaid.redirect_uri', 'http://localhost:8000/thaid/callback');
    config()->set('services.thaid.issuer', 'https://thaid.test');
    cache()->forget('thaid_well_known_config');
});

test('ThaiD provisions and logs in only a CID registered in personnel', function () {
    DB::table('person')->insert(['cid' => '1234567890123', 'pname' => 'นาย', 'fname' => 'สมชาย', 'lname' => 'ใจดี']);
    $this->mock(ThaidIdTokenVerifier::class, function (MockInterface $mock) {
        $mock->shouldReceive('verify')->once()->andReturn([
            'pid' => '1234567890123',
            'name' => 'นายสมชาย ใจดี',
            'name_en' => 'Mr Somchai Jaidee',
            'birthdate' => '1990-01-01',
            'address' => ['formatted' => 'พิษณุโลก'],
        ]);
    });
    Http::fake([
        'https://thaid.test/.well-known' => Http::response([
            'token_endpoint' => 'https://thaid.test/token',
            'jwks_uri' => 'https://thaid.test/jwks',
            'authorization_endpoint' => 'https://thaid.test/authorize',
        ]),
        'https://thaid.test/token' => Http::response(['id_token' => 'synthetic-id-token', 'access_token' => 'synthetic-access-token']),
        'https://thaid.test/jwks' => Http::response(['keys' => []]),
    ]);

    $this->withSession(['thaid_state' => 'state', 'thaid_nonce' => 'nonce'])
        ->get('/thaid/callback?state=state&code=code')
        ->assertRedirect(route('home'))
        ->assertSessionMissing('thaid_access_token');

    expect(DB::table('users')->where('pid', '1234567890123')->exists())->toBeTrue();
});
