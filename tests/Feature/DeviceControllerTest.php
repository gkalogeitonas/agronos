<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\User;
use App\Services\MqttCredentialService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(RefreshDatabase::class);

describe('DeviceController@createMqttCredentials', function () {

    it('creates mqtt credentials for a device', function () {
        $user = User::factory()->create();
        $device = Device::factory()->create(['user_id' => $user->id]);

        $mockService = \Mockery::mock(MqttCredentialService::class);
        $mockService->shouldReceive('createCredentials')
            ->once()
            ->andReturn([
                'mqtt_broker_url' => 'mqtt://broker.local',
                'username' => $device->uuid,
                'password' => 'test-password',
                'created' => true,
            ]);

        $this->app->instance(MqttCredentialService::class, $mockService);

        $response = $this->actingAs($user)
            ->post(route('devices.mqtt.create', $device));

        $response->assertRedirect(route('devices.show', $device));
        $response->assertSessionHas('mqtt_credentials', function ($credentials) {
            return $credentials['created'] === true
                && $credentials['username'] !== null
                && $credentials['password'] !== null;
        });
    });

    it('returns existing credentials without creating new ones', function () {
        $user = User::factory()->create();
        $device = Device::factory()->create([
            'user_id' => $user->id,
            'mqtt_username' => 'existing-user',
            'mqtt_password' => 'existing-pass',
        ]);

        $mockService = \Mockery::mock(MqttCredentialService::class);
        $mockService->shouldReceive('createCredentials')
            ->once()
            ->andReturn([
                'mqtt_broker_url' => 'mqtt://broker.local',
                'username' => 'existing-user',
                'password' => 'existing-pass',
                'created' => false,
            ]);

        $this->app->instance(MqttCredentialService::class, $mockService);

        $response = $this->actingAs($user)
            ->post(route('devices.mqtt.create', $device));

        $response->assertSessionHas('mqtt_credentials', function ($credentials) {
            return $credentials['created'] === false;
        });
    });

    it('flashes error when emqx is unavailable', function () {
        $user = User::factory()->create();
        $device = Device::factory()->create(['user_id' => $user->id]);

        $mockService = \Mockery::mock(MqttCredentialService::class);
        $mockService->shouldReceive('createCredentials')
            ->once()
            ->andReturn([
                'mqtt_broker_url' => 'mqtt://broker.local',
                'created' => false,
                'error' => 'emqx_unavailable',
            ]);

        $this->app->instance(MqttCredentialService::class, $mockService);

        $response = $this->actingAs($user)
            ->post(route('devices.mqtt.create', $device));

        $response->assertSessionHas('mqtt_credentials', function ($credentials) {
            return $credentials['error'] === 'emqx_unavailable';
        });
    });

    it('requires authentication', function () {
        $device = Device::factory()->create();

        $response = $this->post(route('devices.mqtt.create', $device));

        $response->assertRedirect(route('login'));
    });
});
