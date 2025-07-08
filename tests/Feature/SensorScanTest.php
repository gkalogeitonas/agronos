<?php

use App\Models\Device;
use App\Models\Sensor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use function Pest\Laravel\actingAs;
use function Pest\Laravel\postJson;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->device = Device::factory(['user_id' => $this->user->id])->create();
});

it('creates a new sensor on first scan', function () {
    actingAs($this->user);
    $payload = [
        'device_uuid' => $this->device->uuid,
        'uuid' => 'test-uuid-123',
        'lat' => 10.1234567,
        'lon' => 20.7654321,
    ];
    $response = postJson(route('sensors.scan'), $payload);
    $response->assertStatus(201)
        ->assertJson(['message' => 'Sensor created successfully']);
    $this->assertDatabaseHas('sensors', ['uuid' => 'test-uuid-123', 'device_id' => $this->device->id]);
});

it('updates an existing sensor on repeated scan', function () {
    actingAs($this->user);
    $sensor = Sensor::factory()->create([
        'user_id' => $this->user->id,
        'device_id' => $this->device->id,
        'uuid' => 'test-uuid-123',
        'lat' => 1.0,
        'lon' => 2.0,
        'type' => 'moisture',
        'name' => 'Old Name',
    ]);
    $payload = [
        'device_uuid' => $this->device->uuid,
        'uuid' => 'test-uuid-123',
        'lat' => 99.9999999,
        'lon' => 88.8888888,
        'type' => 'humidity',
        'name' => 'Updated Name',
    ];
    $response = postJson(route('sensors.scan'), $payload);
    $response->assertStatus(200)
        ->assertJson(['message' => 'Sensor updated successfully']);
    $this->assertDatabaseHas('sensors', [
        'uuid' => 'test-uuid-123',
        'device_id' => $this->device->id,
        'lat' => 99.9999999,
        'lon' => 88.8888888,
        'type' => 'humidity',
        'name' => 'Updated Name',
    ]);
});
