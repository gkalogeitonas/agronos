<?php

use App\Services\EmqxService;
use Illuminate\Support\Facades\Http;

it('sends correct request when creating a user', function () {
    // Arrange: configure EMQX service values used by the service
    config([
        'services.emqx.url' => 'http://emqx.test',
        'services.emqx.base_path' => '/api/v5',
        'services.emqx.authenticator_id' => 'password_based:built_in_database',
        'services.emqx.api_key' => 'api-key',
        'services.emqx.secret_key' => 'secret',
    ]);

    // Fake all HTTP requests and return a simple successful JSON response
    Http::fake([
        '*' => Http::response(['result' => 'ok'], 200),
    ]);

    $svc = new EmqxService;

    // Act
    $resp = $svc->createUser('user1', 'p@ss');

    // Assert: service returns decoded JSON
    expect($resp)->toBeArray()->toHaveKey('result');

    // Assert: the HTTP client sent the expected POST with user_id and password
    Http::assertSent(function ($request) {
        $expectedUrl = 'http://emqx.test/api/v5/authentication/password_based:built_in_database/users';
        $body = $request->data();

        return $request->method() === 'POST'
            && $request->url() === $expectedUrl
            && isset($body['user_id'])
            && $body['user_id'] === 'user1'
            && isset($body['password'])
            && $body['password'] === 'p@ss';
    });
});

it('sends correct rules when authorizing a user with defaults', function () {
    config([
        'services.emqx.url' => 'http://emqx.test',
        'services.emqx.base_path' => '/api/v5',
        'services.emqx.authenticator_id' => 'password_based:built_in_database',
        'services.emqx.api_key' => 'api-key',
        'services.emqx.secret_key' => 'secret',
    ]);

    Http::fake([
        '*' => Http::response(null, 200),
    ]);

    $svc = new EmqxService;

    $rules = [
        ['permission' => 'allow', 'topic' => 'devices/device1/#'],
        ['permission' => 'deny', 'topic' => '#'],
    ];

    $result = $svc->authorizeUser('device1', $rules);

    expect($result)->toBeTrue();

    Http::assertSent(function ($request) {
        $expectedUrl = 'http://emqx.test/api/v5/authorization/sources/built_in_database/rules/users';
        $body = $request->data();

        // Body should be an array with one element containing rules and username
        if (! is_array($body) || count($body) < 1) {
            return false;
        }

        $entry = $body[0];

        return $request->method() === 'POST'
            && $request->url() === $expectedUrl
            && isset($entry['username'])
            && $entry['username'] === 'device1'
            && isset($entry['rules'])
            && is_array($entry['rules'])
            && collect($entry['rules'])->contains(fn ($r) => ($r['permission'] ?? null) === 'allow' && ($r['topic'] ?? '') === 'devices/device1/#');
    });
});
