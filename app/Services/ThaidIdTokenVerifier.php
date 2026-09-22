<?php

namespace App\Services;

use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use UnexpectedValueException;

class ThaidIdTokenVerifier
{
    public function verify(string $token, array $jwks, string $issuer, string $audience, string $nonce): array
    {
        $allowedKeys = array_values(array_filter(
            $jwks['keys'] ?? [],
            fn (array $key): bool => ($key['kty'] ?? null) === 'EC'
                && ($key['crv'] ?? null) === 'P-256'
                && (!isset($key['alg']) || $key['alg'] === 'ES256')
                && (($key['use'] ?? 'sig') === 'sig')
        ));

        if ($allowedKeys === []) {
            throw new UnexpectedValueException('No allowed ThaiD signing keys are available.');
        }

        $claims = (array) JWT::decode($token, JWK::parseKeySet(['keys' => $allowedKeys], 'ES256'));

        if (($claims['iss'] ?? null) !== $issuer) {
            throw new UnexpectedValueException('ThaiD token issuer is invalid.');
        }

        $tokenAudiences = is_array($claims['aud'] ?? null) ? $claims['aud'] : [$claims['aud'] ?? null];

        if (!in_array($audience, $tokenAudiences, true)) {
            throw new UnexpectedValueException('ThaiD token audience is invalid.');
        }

        if (count($tokenAudiences) > 1 && ($claims['azp'] ?? null) !== $audience) {
            throw new UnexpectedValueException('ThaiD token authorized party is invalid.');
        }

        if (!isset($claims['exp'], $claims['iat']) || !is_numeric($claims['exp']) || !is_numeric($claims['iat'])) {
            throw new UnexpectedValueException('ThaiD token time claims are incomplete.');
        }

        if (!isset($claims['nonce']) || !is_string($claims['nonce']) || !hash_equals($nonce, $claims['nonce'])) {
            throw new UnexpectedValueException('ThaiD token nonce is invalid.');
        }

        return $claims;
    }
}
