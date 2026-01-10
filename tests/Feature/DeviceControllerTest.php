<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\User;
use App\Services\EmqxService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('DeviceController@createMqttCredentials', function () {

    it('creates mqtt credentials for a device', function () {
        $user = User::factory()->create();
        $device = Device::factory()->create(['user_id' => $user->id]);

        $mockEmqx = \Mockery::mock(EmqxService::class);
        $mockEmqx->shouldReceive('createUser')
            ->once()
            ->with($device->uuid, \Mockery::type('string'))
            ->andReturn(['user_id' => $device->uuid]);

        $this->app->instance(EmqxService::class, $mockEmqx);

        $response = $this->actingAs($user)
            ->post(route('devices.mqtt.create', $device));

        $response->assertRedirect(route('devices.show', $device));
        $response->assertSessionHas('mqtt_credentials', function ($credentials) {
            return $credentials['created'] === true
                && $credentials['username'] !== null
                && $credentials['password'] !== null;
        });

        $device->refresh();
        expect($device->mqtt_username)->toBe($device->uuid);
        expect($device->mqtt_password)->not->toBeNull();
        //assert also database has the updated credentials
        $this->assertDatabaseHas('devices', [
            'id' => $device->id,
            'mqtt_username' => $device->uuid,
            'mqtt_password' => $device->mqtt_password,
        ]);
    });

    it('returns existing credentials without creating new ones', function () {
        $user = User::factory()->create();
        $device = Device::factory()->create([
            'user_id' => $user->id,
            'mqtt_username' => 'existing-user',
            'mqtt_password' => 'existing-pass',
        ]);

        $mockEmqx = \Mockery::mock(EmqxService::class);
        $mockEmqx->shouldNotReceive('createUser');

        $this->app->instance(EmqxService::class, $mockEmqx);

        $response = $this->actingAs($user)
            ->post(route('devices.mqtt.create', $device));

        $response->assertSessionHas('mqtt_credentials', function ($credentials) {
            return $credentials['created'] === false
                && $credentials['username'] === 'existing-user'
                && $credentials['password'] === 'existing-pass';
        });
    });


    it('flashes error when emqx returns error response', function () {
        $user = User::factory()->create();
        $device = Device::factory()->create(['user_id' => $user->id]);

        $mockEmqx = \Mockery::mock(EmqxService::class);
        $mockEmqx->shouldReceive('createUser')
            ->once()
            ->andReturn([
                'status' => 400,
                'body' => 'Bad request',
            ]);

        $this->app->instance(EmqxService::class, $mockEmqx);

        $response = $this->actingAs($user)
            ->post(route('devices.mqtt.create', $device));

        $response->assertSessionHas('mqtt_credentials', function ($credentials) {
            return $credentials['created'] === false
                && $credentials['error'] === 'emqx_unavailable';
        });

        $device->refresh();
        expect($device->mqtt_username)->toBeNull();
        expect($device->mqtt_password)->toBeNull();
    });

    it('requires authentication', function () {
        $device = Device::factory()->create();

        $response = $this->post(route('devices.mqtt.create', $device));

        $response->assertRedirect(route('login'));
    });
});
