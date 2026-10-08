<?php

const LINE_IN_APP_USER_AGENT = 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/15E148 Safari Line/14.16.1';
const SAFARI_USER_AGENT = 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.0 Mobile/15E148 Safari/604.1';

test('login entry points opened inside LINE reload with openExternalBrowser', function (string $route) {
    $this->withHeader('User-Agent', LINE_IN_APP_USER_AGENT)
        ->get(route($route))
        ->assertRedirect(route($route).'?openExternalBrowser=1');
})->with(['login', 'register', 'thaid.redirect', 'provider.redirect']);

test('the openExternalBrowser reload keeps the existing query string', function () {
    $response = $this->withHeader('User-Agent', LINE_IN_APP_USER_AGENT)->get('/login?from=line');

    expect($response->headers->get('Location'))
        ->toContain('from=line')
        ->toContain('openExternalBrowser=1');
});

test('LINE requests that already carry openExternalBrowser are not redirected again', function () {
    $this->withHeader('User-Agent', LINE_IN_APP_USER_AGENT)
        ->get(route('login', ['openExternalBrowser' => 1]))
        ->assertOk();
});

test('other browsers open the login page normally', function () {
    $this->withHeader('User-Agent', SAFARI_USER_AGENT)
        ->get(route('login'))
        ->assertOk();
});

test('SSO callbacks are never redirected out of the browser that started the login', function () {
    $response = $this->withHeader('User-Agent', LINE_IN_APP_USER_AGENT)->get(route('thaid.callback'));

    expect((string) $response->headers->get('Location'))->not->toContain('openExternalBrowser');
});
