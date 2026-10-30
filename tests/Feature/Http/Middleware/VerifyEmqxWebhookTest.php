<?php

beforeEach(function () {
    config(['services.emqx.webhook_secret' => 'emqx-webhook-secret']);
});

dataset('emqx webhooks', [
    'device' => '/api/v1/device/mqtt-webhook',
    'lora' => '/api/v1/lora/webhook',
]);

it('returns 401 when the X-EMQX-Secret header is missing', function (string $webhook) {
    $this->postJson($webhook, ['username' => 'device-1', 'payload' => '{}'])
        ->assertUnauthorized();
})->with('emqx webhooks');

it('returns 401 when the X-EMQX-Secret header is wrong', function (string $webhook) {
    $this->withHeader('X-EMQX-Secret', 'wrong-secret')
        ->postJson($webhook, ['username' => 'device-1', 'payload' => '{}'])
        ->assertUnauthorized();
})->with('emqx webhooks');

it('returns 401 for every request while no webhook secret is configured', function (string $webhook) {
    config(['services.emqx.webhook_secret' => null]);

    $this->withHeader('X-EMQX-Secret', '')
        ->postJson($webhook, ['username' => 'device-1', 'payload' => '{}'])
        ->assertUnauthorized();
})->with('emqx webhooks');
