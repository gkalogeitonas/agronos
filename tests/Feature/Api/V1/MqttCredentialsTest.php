<?php

use App\Enums\DeviceStatus;
use App\Models\Device;
use App\Services\EmqxService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates mqtt credentials on first request and returns created=true', function () {
    $device = Device::factory()->create([
        'uuid' => 'device-abc',
        'status' => DeviceStatus::OFFLINE,
    ]);

    $token = $device->createToken('device-token')->plainTextToken;

    // mock EmqxService in the container
    app()->instance(EmqxService::class, new class
    {
        public function createUser($u, $p)
        {
            return true;
        }
    });

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/v1/device/mqtt-credentials');

    // dd($response->json());
    $response->assertStatus(200)->assertJson(['created' => true, 'topic_base' => 'agronos/devices/device-abc']);
    $device->refresh();
    expect($device->mqtt_username)->toBe('device-abc');
    expect($device->mqtt_password)->not->toBeNull();
});

it('returns existing creds and created=false on subsequent calls', function () {
    $device = Device::factory()->create([
        'uuid' => 'device-xyz',
        'status' => DeviceStatus::OFFLINE,
        'mqtt_username' => 'device-xyz',
        'mqtt_password' => 'secret123',
    ]);

    $token = $device->createToken('device-token')->plainTextToken;

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/v1/device/mqtt-credentials');

    $response->assertStatus(200)->assertJson(['created' => false, 'username' => 'device-xyz', 'topic_base' => 'agronos/devices/device-xyz']);
});

it('returns the topic base under the configured prefix', function () {
    config(['services.emqx.topic_prefix' => 'farm-a']);
    $device = Device::factory()->create([
        'uuid' => 'device-xyz',
        'mqtt_username' => 'device-xyz',
        'mqtt_password' => 'secret123',
    ]);

    $token = $device->createToken('device-token')->plainTextToken;

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/v1/device/mqtt-credentials')
        ->assertOk()
        ->assertJson(['topic_base' => 'farm-a/devices/device-xyz']);
});

it('does not create credentials when broker is offline and returns no error', function () {
    $device = Device::factory()->create([
        'uuid' => 'device-offline',
        'status' => DeviceStatus::OFFLINE,
    ]);

    $token = $device->createToken('device-token')->plainTextToken;

    // Mock EmqxService in the container to simulate broker failure
    app()->instance(EmqxService::class, new class
    {
        public function createUser($u, $p)
        {
            return ['status' => 500, 'body' => 'service unavailable'];
        }
    });

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/v1/device/mqtt-credentials');

    $response->assertStatus(503)->assertJson(['created' => false]);

    $device->refresh();
    expect($device->mqtt_username)->toBeNull();
    expect($device->mqtt_password)->toBeNull();
});
