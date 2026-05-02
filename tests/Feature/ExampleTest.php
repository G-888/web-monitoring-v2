<?php

beforeEach(function () {
    $this->withoutMiddleware([
        \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
        \Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class,
    ]);
});

it('redirects guests to the login page', function () {
    $response = $this->get('/');

    $response->assertRedirect('/login');
});
