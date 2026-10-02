<?php

use Illuminate\Support\Facades\Route;

beforeEach(function () {
    Route::get('/_proxy-check', fn () => [
        'secure' => request()->isSecure(),
        'root' => url('/'),
    ]);
});

test('requests forwarded over https by a reverse proxy are treated as secure', function () {
    $this->get('http://agronos.test/_proxy-check', [
        'X-Forwarded-Proto' => 'https',
        'X-Forwarded-Host' => 'agronos.test',
        'X-Forwarded-Port' => '443',
    ])->assertOk()->assertJson([
        'secure' => true,
        'root' => 'https://agronos.test',
    ]);
});

test('plain http requests without forwarded headers stay insecure', function () {
    $this->get('http://agronos.test/_proxy-check')
        ->assertOk()
        ->assertJson([
            'secure' => false,
            'root' => 'http://agronos.test',
        ]);
});
