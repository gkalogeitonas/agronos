<?php

use App\Models\Device;
use App\Models\Farm;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

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

    $device = Device::allTenants()->where('uuid', $deviceData['uuid'])->sole();
    $response->assertRedirect(route('devices.show', $device));
    $response->assertSessionHas('success', 'Device registered successfully');

    expect($device)
        ->name->toBe('Test Soil Sensor')
        ->user_id->toBe($this->user->id)
        ->farm_id->toBeNull()
        ->status->value->toBe('registered');
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
    expect(Hash::check($plainSecret, $device->secret))->toBeTrue();
});

test('a grower can register a device on one of their farms', function () {
    $farm = Farm::factory()->create(['user_id' => $this->user->id]);

    $response = $this->actingAs($this->user)->post(route('devices.store'), [
        'uuid' => 'device-on-farm',
        'secret' => 'a-secret',
        'type' => 'wifi',
        'farm_id' => $farm->id,
    ]);

    $response->assertSessionHasNoErrors();
    $device = Device::query()->where('uuid', 'device-on-farm')->sole();
    $response->assertRedirect(route('devices.show', $device));
    expect($device->farm_id)->toBe($farm->id);
});

test('registering a device on another tenant\'s farm fails validation', function () {
    $otherFarm = Farm::factory()->create();

    $this->actingAs($this->user)
        ->post(route('devices.store'), [
            'uuid' => 'device-on-other-farm',
            'secret' => 'a-secret',
            'type' => 'wifi',
            'farm_id' => $otherFarm->id,
        ])
        ->assertSessionHasErrors('farm_id');

    expect(Device::allTenants()->where('uuid', 'device-on-other-farm')->exists())->toBeFalse();
});
