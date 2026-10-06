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

test('HealthID login starts at the documented redirect endpoint and forces authentication', function () {
    config()->set('services.provider.healthid_client_id', 'test-client');

    $url = Livewire::test(Redirect::class)->get('url');
    parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

    expect(parse_url($url, PHP_URL_SCHEME))->toBe('https')
        ->and(parse_url($url, PHP_URL_HOST))->toBe('moph.id.th')
        ->and(parse_url($url, PHP_URL_PATH))->toBe('/oauth/redirect')
        ->and($query['is_auth'] ?? null)->toBe('yes')
        ->and($query['response_type'] ?? null)->toBe('code');
});
