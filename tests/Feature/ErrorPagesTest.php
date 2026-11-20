<?php

use Illuminate\Support\Facades\View;

it('returns 404 and renders the custom errors.404 view for an unknown route', function () {
    config(['app.debug' => false]);

    $this->get('/halaman-yang-tidak-pernah-ada')
        ->assertNotFound()
        ->assertSee('Error 404')
        ->assertSee('Halaman tidak ditemukan')
        ->assertSee('images/errors/404.svg')
        ->assertSee('SIGAP-Alkes');
});

it('renders custom error views 403, 500, and 503 without error', function (string $code) {
    $html = View::make("errors.{$code}")->render();

    expect($html)
        ->toContain("Error {$code}")
        ->toContain('SIGAP-Alkes')
        ->toContain("images/errors/{$code}.svg");
})->with(['401', '403', '404', '419', '429', '500', '503']);
