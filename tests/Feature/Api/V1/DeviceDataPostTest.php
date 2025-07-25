<?php

use Illuminate\Support\Facades\Log;
use App\Models\Device;
use Laravel\Sanctum\Sanctum;
use Illuminate\Support\Facades\Hash;

it('allows a device to post data with a valid token', function () {
    $device = Device::factory()->create();
    $token = $device->createToken('device-token')->plainTextToken;

    $response = $this->withToken($token)
        ->postJson('/api/v1/device/data', [
            'temperature' => 25.5,
            'humidity' => 60,
        ]);

    $response->assertStatus(200)
        ->assertJson(['message' => 'Data received']);
});

it('rejects a device posting data with an invalid token', function () {
    $response = $this->withToken('invalid-token')
        ->postJson('/api/v1/device/data', [
            'temperature' => 25.5,
            'humidity' => 60,
        ]);

    $response->assertStatus(401);
});

it('rejects a device posting data with no token', function () {
    $response = $this->postJson('/api/v1/device/data', [
        'temperature' => 25.5,
        'humidity' => 60,
    ]);

    $response->assertStatus(401);
});

it('device can login and then post data with the received token', function () {
    // Create device with known credentials
    $device = Device::factory()->create([
        'uuid' => 'end-to-end-device-uuid',
        'secret' => Hash::make('end-to-end-secret'),
    ]);

    // Device logs in to get token
    $loginResponse = $this->postJson('/api/v1/device/login', [
        'uuid' => 'end-to-end-device-uuid',
        'secret' => 'end-to-end-secret',
    ]);

    $loginResponse->assertStatus(200)->assertJsonStructure(['token']);
    $token = $loginResponse->json('token');

    // Device uses token to post data
    $dataResponse = $this->withToken($token)
        ->postJson('/api/v1/device/data', [
            'temperature' => 22.3,
            'humidity' => 55,
        ]);

    $dataResponse->assertStatus(200)
        ->assertJson(['message' => 'Data received']);
});
