<?php

use App\Models\Device;
use App\Enums\DeviceStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates mqtt credentials on first request and returns created=true', function () {
    $device = Device::factory()->create([
        'uuid' => 'device-abc',
        'status' => DeviceStatus::OFFLINE,
    ]);

    $token = $device->createToken('device-token')->plainTextToken;

    // mock emqx app binding
    app()->bind('emqx', function () {
        return new class {
            public function createUser($u, $p) { return true; }
        };
    });

    $response = $this->withHeader('Authorization', 'Bearer ' . $token)
        ->getJson('/api/v1/device/mqtt-credentials');

    //dd($response->json());
    $response->assertStatus(200)->assertJson(['created' => true]);
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

    $response = $this->withHeader('Authorization', 'Bearer ' . $token)
        ->getJson('/api/v1/device/mqtt-credentials');

    $response->assertStatus(200)->assertJson(['created' => false, 'username' => 'device-xyz']);
});
