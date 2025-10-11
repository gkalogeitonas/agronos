<?php

use App\Models\Device;
use App\Enums\DeviceStatus;
use App\Services\SensorDataService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('processes mqtt broker webhook and updates device status', function () {
    // create a device with uuid that will be used as username
    $device = Device::factory()->create([
        'uuid' => 'Test-Device-1',
        'status' => DeviceStatus::OFFLINE,
    ]);

    // Mock the SensorDataService and assert processSensorData is called with device and sensors
    $mock = mock(SensorDataService::class);
    // Bind the mock into the service container so the controller receives it
    $this->app->instance(SensorDataService::class, $mock);
    $mock->shouldReceive('processSensorData')
        ->once()
        ->withArgs(function ($passedDevice, $sensors) use ($device) {
            return $passedDevice->id === $device->id
                && is_array($sensors)
                && count($sensors) === 3
                && $sensors[0]['uuid'] === 'Test-Device-1-sensor-1';
        })
        ->andReturn(['processed' => true]);

    // Prepare the webhook payload as the broker would send
    $payload = json_encode([
        'sensors' => [
            ['uuid' => 'Test-Device-1-sensor-1', 'value' => 10.0],
            ['uuid' => 'Test-Device-1-sensor-2', 'value' => 20.9],
            ['uuid' => 'Test-Device-1-sensor-3', 'value' => 99.9],
        ],
    ]);

    $response = $this->postJson('/api/v1/device/mqtt-webhook', [
        'username' => 'Test-Device-1',
        'payload' => $payload,
    ]);

    $response->assertStatus(200)->assertJson(['message' => 'Webhook received']);

    $device->refresh();
    expect($device->status)->toBe(DeviceStatus::ONLINE);
});

it('updates sensors last_reading and last_reading_at from payload', function () {
    // create a device
    $device = Device::factory()->create([
        'uuid' => 'Test-Device-1',
        'status' => DeviceStatus::OFFLINE,
    ]);

    // create sensors belonging to that device
    $sensor1 = \App\Models\Sensor::factory()->create([
        'uuid' => 'Test-Device-1-sensor-1',
        'device_id' => $device->id,
        'last_reading' => null,
        'last_reading_at' => null,
    ]);
    $sensor2 = \App\Models\Sensor::factory()->create([
        'uuid' => 'Test-Device-1-sensor-2',
        'device_id' => $device->id,
    ]);
    $sensor3 = \App\Models\Sensor::factory()->create([
        'uuid' => 'Test-Device-1-sensor-3',
        'device_id' => $device->id,
    ]);

    $payload = json_encode([
        'sensors' => [
            ['uuid' => 'Test-Device-1-sensor-1', 'value' => 10.0],
            ['uuid' => 'Test-Device-1-sensor-2', 'value' => 20.9],
            ['uuid' => 'Test-Device-1-sensor-3', 'value' => 100.9],
        ],
    ]);

    $response = $this->postJson('/api/v1/device/mqtt-webhook', [
        'username' => 'Test-Device-1',
        'payload' => $payload,
    ]);

    $response->assertStatus(200)->assertJson(['message' => 'Webhook received']);

    $sensor1->refresh();
    $sensor2->refresh();
    $sensor3->refresh();

    expect((float) $sensor1->last_reading)->toBe(10.0);
    expect((float) $sensor2->last_reading)->toBe(20.9);
    expect((float) $sensor3->last_reading)->toBe(100.9);

    expect($sensor1->last_reading_at)->not->toBeNull();
    expect($sensor2->last_reading_at)->not->toBeNull();
    expect($sensor3->last_reading_at)->not->toBeNull();
});
