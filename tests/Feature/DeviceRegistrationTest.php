<?php

use App\Models\Device;
use App\Models\User;
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

    $response->assertRedirect(route('devices.index'));
    $response->assertSessionHas('success', 'Device registered successfully');

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
        'type' => 'wifi',
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

    // $response->dump();

    $response->assertStatus(422);
});

test('device secret is hashed when device is registered', function () {
    $plainSecret = 'plain-secret-test';
    $deviceData = [
        'uuid' => 'test-hash-uuid-001',
        'secret' => $plainSecret,
        'name' => 'Test Device',
        'type' => 'wifi',
    ];

    $response = $this
        ->actingAs($this->user, 'web')
        ->post(route('devices.store'), $deviceData);

    // dump($response->getContent());

    $device = Device::where('uuid', $deviceData['uuid'])->first();
    // dd($device);
    expect($device)->not->toBeNull();
    expect($device->secret)->not->toBe($plainSecret);
    expect(\Illuminate\Support\Facades\Hash::check($plainSecret, $device->secret))->toBeTrue();
});
