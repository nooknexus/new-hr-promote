<?php

test('guests are redirected to login from the protected home page', function () {
    $response = $this->get('/');

    $response->assertRedirect(route('login'));
});
