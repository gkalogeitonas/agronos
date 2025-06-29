<?php

use App\Models\User;
use App\Models\Farm;
use App\Models\Device;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();

    // Create a device that's already registered by a user but not activated by the device itself yet
    $this->device = Device::create([
        'user_id' => $this->user->id,
        'name' => 'Test Device',
        'uuid' => 'test-device-uuid-1',
        'secret' => bcrypt('device-secret'),
        'type' => 'wifi',
        'status' => 'registered',
    ]);
})->skip();

test('device can register itself with valid credentials', function () {
    $response = $this->postJson(route('api.devices.register'), [
        'uuid' => 'test-device-uuid-1',
        'secret' => 'device-secret'
    ]);

    $response->assertStatus(200)
        ->assertJsonStructure([
            'token',
            'device_id'
        ]);

    $this->assertDatabaseHas('devices', [
        'id' => $this->device->id,
        'status' => 'online',
    ]);
})->skip();

test('device cannot register with invalid credentials', function () {
    $response = $this->postJson(route('api.devices.register'), [
        'uuid' => 'test-device-uuid-1',
        'secret' => 'wrong-secret'
    ]);

    $response->assertStatus(401);

    $this->assertDatabaseHas('devices', [
        'id' => $this->device->id,
        'status' => 'registered', // Status shouldn't change
    ]);
})->skip();

test('device can authenticate and receive new token', function () {
    // First register the device to get a token
    $registerResponse = $this->postJson(route('api.devices.register'), [
        'uuid' => 'test-device-uuid-1',
        'secret' => 'device-secret'
    ]);

    $token = $registerResponse->json('token');

    // Now authenticate with that token
    $response = $this->postJson(route('api.devices.authenticate'), [
        'uuid' => 'test-device-uuid-1',
        'token' => $token
    ]);

    $response->assertStatus(200)
        ->assertJsonStructure([
            'token'
        ]);

    // Ensure new token is different
    $this->assertNotEquals($token, $response->json('token'));
})->skip();

// test('device can send sensor data when authenticated', function () {
//     // First register the device to get a token
//     $registerResponse = $this->postJson(route('api.devices.register'), [
//         'uuid' => 'test-device-uuid-1',
//         'secret' => 'device-secret'
//     ]);

//     $token = $registerResponse->json('token');

//     // Add a sensor to the device for testing
//     $sensor = \App\Models\Sensor::create([
//         'device_id' => $this->device->id,
//         'code' => 'TEST001',
//         'name' => 'Test Sensor',
//         'type' => 'temperature',
//     ]);

//     // Send sensor data
//     $response = $this->withHeaders([
//         'Authorization' => 'Bearer ' . $token,
//     ])->postJson(route('api.devices.data.store'), [
//         'device_id' => $this->device->id,
//         'readings' => [
//             [
//                 'sensor_code' => 'TEST001',
//                 'type' => 'temperature',
//                 'value' => 23.5,
//                 'timestamp' => now()->toIso8601String()
//             ]
//         ]
//     ]);

//     $response->assertStatus(200)
//         ->assertJson([
//             'message' => 'Data received successfully',
//             'processed_readings' => 1,
//         ]);
// });

// test('device cannot send data without authentication', function () {
//     $response = $this->postJson(route('api.devices.data.store'), [
//         'device_id' => $this->device->id,
//         'readings' => [
//             [
//                 'sensor_code' => 'TEST001',
//                 'type' => 'temperature',
//                 'value' => 23.5
//             ]
//         ]
//     ]);

//     $response->assertStatus(401);
// });
