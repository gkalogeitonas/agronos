<?php

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->emqx = 'http://emqx.test/api/v5';
    config([
        'services.emqx.url' => 'http://emqx.test',
        'services.emqx.base_path' => '/api/v5',
        'services.emqx.api_key' => 'api-key',
        'services.emqx.secret_key' => 'secret',
        'services.emqx.webhook_base_url' => 'http://web',
        'services.emqx.webhook_secret' => 'emqx-webhook-secret',
    ]);
    Http::preventStrayRequests();
});

it('creates the device and LoRa connectors, actions and rules when they do not exist', function () {
    Http::fake([
        $this->emqx.'/connectors/*' => Http::response(['code' => 'NOT_FOUND'], 404),
        $this->emqx.'/actions/*' => Http::response(['code' => 'NOT_FOUND'], 404),
        $this->emqx.'/rules/*' => Http::response(['code' => 'NOT_FOUND'], 404),
        $this->emqx.'/connectors' => Http::response([], 201),
        $this->emqx.'/actions' => Http::response([], 201),
        $this->emqx.'/rules' => Http::response([], 201),
    ]);

    $this->artisan('mqtt:setup-webhook', ['--base-url' => 'http://app.test/'])
        ->expectsOutput('Connector agronos_device_webhook: created')
        ->expectsOutput('Action agronos_device_webhook: created')
        ->expectsOutput('Rule agronos_device_sensors: created')
        ->expectsOutput('Connector agronos_lora_webhook: created')
        ->expectsOutput('Action agronos_lora_webhook: created')
        ->expectsOutput('Rule agronos_lora_gateway: created')
        ->assertSuccessful();

    Http::assertSent(fn (Request $request) => $request->method() === 'POST'
        && $request->url() === $this->emqx.'/connectors'
        && $request['type'] === 'http'
        && $request['name'] === 'agronos_device_webhook'
        && $request['url'] === 'http://app.test/api/v1/device/mqtt-webhook'
        && $request['headers']['x-emqx-secret'] === 'emqx-webhook-secret');
    Http::assertSent(fn (Request $request) => $request->method() === 'POST'
        && $request->url() === $this->emqx.'/actions'
        && $request['name'] === 'agronos_device_webhook'
        && $request['connector'] === 'agronos_device_webhook'
        && $request['parameters']['body'] === '${.}');
    Http::assertSent(fn (Request $request) => $request->method() === 'POST'
        && $request->url() === $this->emqx.'/rules'
        && $request['id'] === 'agronos_device_sensors'
        && $request['sql'] === 'SELECT username, payload, topic FROM "devices/+/sensors"'
        && $request['actions'] === ['http:agronos_device_webhook']);

    Http::assertSent(fn (Request $request) => $request->method() === 'POST'
        && $request->url() === $this->emqx.'/connectors'
        && $request['name'] === 'agronos_lora_webhook'
        && $request['url'] === 'http://app.test/api/v1/lora/webhook'
        && $request['headers']['x-emqx-secret'] === 'emqx-webhook-secret');
    Http::assertSent(fn (Request $request) => $request->method() === 'POST'
        && $request->url() === $this->emqx.'/actions'
        && $request['name'] === 'agronos_lora_webhook'
        && $request['connector'] === 'agronos_lora_webhook');
    Http::assertSent(fn (Request $request) => $request->method() === 'POST'
        && $request->url() === $this->emqx.'/rules'
        && $request['id'] === 'agronos_lora_gateway'
        && $request['sql'] === 'SELECT username, payload, topic FROM "lora/+/data"'
        && $request['actions'] === ['http:agronos_lora_webhook']);
});

it('updates the existing resources instead of creating them', function () {
    Http::fake([
        $this->emqx.'/*' => Http::response(['enable' => true]),
    ]);

    $this->artisan('mqtt:setup-webhook')
        ->expectsOutput('Connector agronos_device_webhook: updated')
        ->expectsOutput('Rule agronos_device_sensors: updated')
        ->expectsOutput('Connector agronos_lora_webhook: updated')
        ->expectsOutput('Rule agronos_lora_gateway: updated')
        ->assertSuccessful();

    Http::assertSent(fn (Request $request) => $request->method() === 'PUT'
        && $request->url() === $this->emqx.'/connectors/http:agronos_device_webhook'
        && $request['url'] === 'http://web/api/v1/device/mqtt-webhook'
        && $request['headers']['x-emqx-secret'] === 'emqx-webhook-secret');
    Http::assertSent(fn (Request $request) => $request->method() === 'PUT'
        && $request->url() === $this->emqx.'/connectors/http:agronos_lora_webhook'
        && $request['url'] === 'http://web/api/v1/lora/webhook');
    Http::assertSent(fn (Request $request) => $request->method() === 'PUT'
        && $request->url() === $this->emqx.'/actions/http:agronos_lora_webhook'
        && $request['connector'] === 'agronos_lora_webhook');
    Http::assertSent(fn (Request $request) => $request->method() === 'PUT'
        && $request->url() === $this->emqx.'/rules/agronos_lora_gateway');
    Http::assertNotSent(fn (Request $request) => $request->method() === 'PUT'
        && collect(['type', 'name', 'id'])->contains(fn (string $field) => $request->offsetExists($field)));
    Http::assertNotSent(fn (Request $request) => $request->method() === 'POST');
});

it('fails with the EMQX error and stops before the remaining resources', function () {
    Http::fake([
        $this->emqx.'/connectors/http:agronos_device_webhook' => Http::response(['code' => 'NOT_FOUND'], 404),
        $this->emqx.'/connectors' => Http::response(['code' => 'BAD_REQUEST', 'message' => 'invalid url'], 400),
    ]);

    $this->artisan('mqtt:setup-webhook')
        ->expectsOutputToContain('EMQX rejected the request with HTTP 400: {"code":"BAD_REQUEST","message":"invalid url"}')
        ->assertFailed();

    Http::assertNotSent(fn (Request $request) => str_contains($request->url(), '/actions')
        || str_contains($request->url(), '/rules')
        || str_contains($request->url(), 'agronos_lora_webhook'));
});

it('fails without contacting EMQX when no webhook secret is configured', function () {
    config(['services.emqx.webhook_secret' => null]);
    Http::fake();

    $this->artisan('mqtt:setup-webhook')
        ->expectsOutputToContain('EMQX_WEBHOOK_SECRET')
        ->assertFailed();

    Http::assertNothingSent();
});
