<?php

use App\Models\User;
use Illuminate\Support\Facades\Http;

test('obsolete ProviderID profile refresh endpoints are unavailable', function () {
    $user = new User;
    $user->forceFill(['id' => 99]);
    $user->exists = true;
    $this->actingAs($user);
    Http::preventStrayRequests();

    $this->get('/provider/profile/update')->assertNotFound();
    $this->get('/provider/profile/callback?state=old&code=old')->assertNotFound();
});
