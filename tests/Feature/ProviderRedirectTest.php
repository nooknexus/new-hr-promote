<?php

use App\Livewire\Provider\Redirect;
use Livewire\Livewire;

test('provider redirect creates a session-bound authorization URL', function () {
    config()->set('services.provider.auth_url', 'https://provider.example/authorize');
    config()->set('services.provider.healthid_client_id', 'test-client');

    Livewire::test(Redirect::class)
        ->assertSet('url', fn (string $url): bool => str_contains($url, 'state=')
            && str_contains($url, 'client_id=test-client'));
});
