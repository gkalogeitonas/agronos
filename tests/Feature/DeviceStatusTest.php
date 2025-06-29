<?php

use App\Models\User;
use App\Models\Farm;
use App\Models\Device;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->farm = Farm::factory()->create([
        'user_id' => $this->user->id
    ]);

    // Create a device with a valid registration
    $this->device = Device::create([
        'user_id' => $this->user->id,
        'name' => 'Test Status Device',
        'uuid' => 'status-device-uuid',
        'secret' => bcrypt('device-secret'),
        'type' => 'wifi',
        'status' => 'online',
    ]);

    // Get an authentication token for the device
    $registerResponse = $this->postJson(route('api.devices.register'), [
        'uuid' => 'status-device-uuid',
        'secret' => 'device-secret'
    ]);

    $this->deviceToken = $registerResponse->json('token');
})->skip();

test('device can update its status when authenticated', function () {
    $statusData = [
        'device_id' => $this->device->id,
        'status' => 'online',
        'battery' => 85,
        'signal' => 4
    ];

    $response = $this->withHeaders([
        'Authorization' => 'Bearer ' . $this->deviceToken,
    ])->postJson(route('api.devices.status.update'), $statusData);

    $response->assertStatus(200)
        ->assertJson([
            'message' => 'Status updated successfully',
        ]);

    $this->assertDatabaseHas('devices', [
        'id' => $this->device->id,
        'status' => 'online',
        'battery_level' => 85,
        'signal_strength' => 4
    ]);
})->skip();

test('device cannot update status without authentication', function () {
    $statusData = [
        'device_id' => $this->device->id,
        'status' => 'online',
        'battery' => 85,
        'signal' => 4
    ];

    $response = $this->postJson(route('api.devices.status.update'), $statusData);

    $response->assertStatus(401);
})->skip();

test('device cannot update another device status', function () {
    // Create another device
    $anotherDevice = Device::create([
        'user_id' => $this->user->id,
        'name' => 'Another Device',
        'uuid' => 'another-device-uuid',
        'secret' => bcrypt('another-secret'),
        'type' => 'wifi',
        'status' => 'offline',
    ]);

    $statusData = [
        'device_id' => $anotherDevice->id, // Trying to update another device
        'status' => 'online',
        'battery' => 85,
        'signal' => 4
    ];

    $response = $this->withHeaders([
        'Authorization' => 'Bearer ' . $this->deviceToken,
    ])->postJson(route('api.devices.status.update'), $statusData);

    $response->assertStatus(401);

    $this->assertDatabaseHas('devices', [
        'id' => $anotherDevice->id,
        'status' => 'offline', // Status shouldn't change
    ]);
})->skip();
