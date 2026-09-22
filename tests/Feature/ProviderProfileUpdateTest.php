<?php

use App\Livewire\Provider\ProfileUpdate;
use App\Models\User;
use Livewire\Livewire;

test('provider profile update binds the callback to the authenticated user session', function () {
    config()->set('services.provider.auth_url', 'https://provider.example/authorize');
    config()->set('services.provider.healthid_client_id', 'test-client');

    $user = new User;
    $user->forceFill(['id' => 99]);
    $user->exists = true;
    $this->actingAs($user);

    Livewire::test(ProfileUpdate::class);

    expect(session('profile_update'))->toBeTrue()
        ->and(session('user_id_for_update'))->toBe(99);
});
