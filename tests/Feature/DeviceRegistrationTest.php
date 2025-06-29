<?php

use App\Models\User;
use App\Models\Farm;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
});

test('authenticated user can register a device with valid data', function () {
    $deviceData = [
        'uuid' => 'test-device-uuid-'.time(),
        'secret' => 'test-secret-key',
        'name' => 'Test Soil Sensor',
        'type' => 'wifi',
    ];

    $response = $this
        ->actingAs($this->user, 'web')
        ->post(route('devices.store'), $deviceData);

    $response->assertStatus(201)
        ->assertJson([
            'message' => 'Device registered successfully',
        ]);

    $this->assertDatabaseHas('devices', [
        'uuid' => $deviceData['uuid'],
        'name' => $deviceData['name'],
        'user_id' => $this->user->id,
        'status' => 'registered',
    ]);
});

test('guest cannot register a device', function () {
    $deviceData = [
        'uuid' => 'test-device-uuid-'.time(),
        'secret' => 'test-secret-key',
        'name' => 'Test Soil Sensor',
        'type' => 'wifi'
    ];

    $response = $this->post(route('devices.store'), $deviceData);

    $response->assertRedirect(route('login'));
});


test('device registration requires valid data', function () {

    $deviceData = [
        'name' => 'Test Soil Sensor',
        'type' => 'wifi',
    ];

    $response = $this
        ->actingAs($this->user, 'web')
        ->withHeaders(['Accept' => 'application/json'])
        ->postJson(route('devices.store'), $deviceData);

    //$response->dump();

    $response->assertStatus(422);
});
