<?php

use App\Services\ThaidIdTokenVerifier;
use Firebase\JWT\JWT;

function base64Url(string $value): string
{
    return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
}

function thaidKeyPair(): array
{
    $privateKey = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
    openssl_pkey_export($privateKey, $privatePem);
    $details = openssl_pkey_get_details($privateKey);

    return [$privatePem, [
        'keys' => [[
            'kty' => 'EC',
            'kid' => 'test-key',
            'alg' => 'ES256',
            'use' => 'sig',
            'crv' => 'P-256',
            'x' => base64Url($details['ec']['x']),
            'y' => base64Url($details['ec']['y']),
        ]],
    ]];
}

function thaidEcKeyPair(): array
{
    $privateKey = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
    openssl_pkey_export($privateKey, $privatePem);
    $details = openssl_pkey_get_details($privateKey);

    return [$privatePem, [
        'keys' => [[
            'kty' => 'EC',
            'kid' => 'test-ec-key',
            'alg' => 'ES256',
            'use' => 'sig',
            'crv' => 'P-256',
            'x' => base64Url($details['ec']['x']),
            'y' => base64Url($details['ec']['y']),
        ]],
    ]];
}

test('it accepts a signed ThaiD token bound to this client and nonce', function () {
    [$privateKey, $jwks] = thaidKeyPair();
    $token = JWT::encode([
        'iss' => 'https://issuer.example',
        'aud' => 'hr-promote',
        'exp' => time() + 300,
        'iat' => time(),
        'nonce' => 'expected-nonce',
        'pid' => '1234567890123',
    ], $privateKey, 'ES256', 'test-key');

    $claims = (new ThaidIdTokenVerifier)->verify($token, $jwks, 'https://issuer.example', 'hr-promote', 'expected-nonce');

    expect($claims['pid'])->toBe('1234567890123');
});

test('it rejects a signed ThaiD token for another audience', function () {
    [$privateKey, $jwks] = thaidKeyPair();
    $token = JWT::encode([
        'iss' => 'https://issuer.example',
        'aud' => 'another-client',
        'exp' => time() + 300,
        'iat' => time(),
        'nonce' => 'expected-nonce',
        'pid' => '1234567890123',
    ], $privateKey, 'ES256', 'test-key');

    (new ThaidIdTokenVerifier)->verify($token, $jwks, 'https://issuer.example', 'hr-promote', 'expected-nonce');
})->throws(UnexpectedValueException::class, 'audience');

test('it accepts an ES256 JWK that omits its optional algorithm field', function () {
    [$privateKey, $jwks] = thaidKeyPair();
    unset($jwks['keys'][0]['alg']);

    $token = JWT::encode([
        'iss' => 'https://issuer.example',
        'aud' => 'hr-promote',
        'exp' => time() + 300,
        'iat' => time(),
        'nonce' => 'expected-nonce',
        'pid' => '1234567890123',
    ], $privateKey, 'ES256', 'test-key');

    $claims = (new ThaidIdTokenVerifier)->verify($token, $jwks, 'https://issuer.example', 'hr-promote', 'expected-nonce');

    expect($claims['pid'])->toBe('1234567890123');
});

test('it accepts a signed ES256 ThaiD token from an EC JWK', function () {
    [$privateKey, $jwks] = thaidEcKeyPair();
    $token = JWT::encode([
        'iss' => 'https://imauth.bora.dopa.go.th',
        'aud' => 'hr-promote',
        'exp' => time() + 300,
        'iat' => time(),
        'nonce' => 'expected-nonce',
        'pid' => '1234567890123',
    ], $privateKey, 'ES256', 'test-ec-key');

    $claims = (new ThaidIdTokenVerifier)->verify($token, $jwks, 'https://imauth.bora.dopa.go.th', 'hr-promote', 'expected-nonce');

    expect($claims['pid'])->toBe('1234567890123');
});
