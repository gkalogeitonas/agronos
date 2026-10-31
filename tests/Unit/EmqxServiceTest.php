<?php

use App\Services\EmqxService;
use Illuminate\Http\Client\Request;
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

it('allows the user only its own topic base when authorizing', function () {
    config([
        'services.emqx.url' => 'http://emqx.test',
        'services.emqx.base_path' => '/api/v5',
        'services.emqx.api_key' => 'api-key',
        'services.emqx.secret_key' => 'secret',
    ]);

    Http::preventStrayRequests();
    Http::fake([
        'http://emqx.test/api/v5/authorization/sources/built_in_database/rules/users/device1' => Http::response(null, 204),
    ]);

    $result = (new EmqxService)->authorizeUser('device1', 'agronos/devices/device1');

    expect($result)->toBeTrue();

    Http::assertSent(fn (Request $request) => $request->method() === 'PUT'
        && $request->data() === [
            'username' => 'device1',
            'rules' => [
                ['action' => 'all', 'permission' => 'allow', 'topic' => 'agronos/devices/device1/#'],
            ],
        ]);
});

it('updates the password when creating a user that already exists', function () {
    config([
        'services.emqx.url' => 'http://emqx.test',
        'services.emqx.base_path' => '/api/v5',
        'services.emqx.authenticator_id' => 'password_based:built_in_database',
        'services.emqx.api_key' => 'api-key',
        'services.emqx.secret_key' => 'secret',
    ]);

    Http::preventStrayRequests();
    Http::fake([
        'http://emqx.test/api/v5/authentication/password_based:built_in_database/users' => Http::response(['code' => 'ALREADY_EXISTS'], 409),
        'http://emqx.test/api/v5/authentication/password_based:built_in_database/users/device1' => Http::response(['user_id' => 'device1'], 200),
    ]);

    $result = (new EmqxService)->createUser('device1', 'p@ss');

    expect($result)->toBe(['user_id' => 'device1']);

    Http::assertSent(fn (Request $request) => $request->method() === 'PUT'
        && $request->url() === 'http://emqx.test/api/v5/authentication/password_based:built_in_database/users/device1'
        && $request->data() === ['password' => 'p@ss']);
});
