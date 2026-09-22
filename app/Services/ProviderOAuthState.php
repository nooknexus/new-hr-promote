<?php

namespace App\Services;

use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Str;

class ProviderOAuthState
{
    private const SESSION_KEY = 'provider_oauth_state';

    public static function issue(Session $session): string
    {
        $state = Str::random(64);

        $session->put(self::SESSION_KEY, $state);

        return $state;
    }

    public static function consume(Session $session, ?string $state): bool
    {
        $expectedState = $session->pull(self::SESSION_KEY);

        return is_string($state)
            && is_string($expectedState)
            && hash_equals($expectedState, $state);
    }
}
