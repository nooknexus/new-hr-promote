<?php

use App\Services\ProviderOAuthState;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;

function providerOAuthSession(): Store
{
    $session = new Store('test', new ArraySessionHandler(5));
    $session->start();

    return $session;
}

test('it accepts a state once for the browser session that initiated it', function () {
    $session = providerOAuthSession();
    $state = ProviderOAuthState::issue($session);

    expect(ProviderOAuthState::consume($session, $state))->toBeTrue()
        ->and(ProviderOAuthState::consume($session, $state))->toBeFalse();
});

test('it rejects a state from a different browser session', function () {
    $initiatingSession = providerOAuthSession();
    $state = ProviderOAuthState::issue($initiatingSession);

    expect(ProviderOAuthState::consume(providerOAuthSession(), $state))->toBeFalse();
});
