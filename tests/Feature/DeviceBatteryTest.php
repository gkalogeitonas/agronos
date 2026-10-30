<?php

use App\Enums\SensorType;
use App\Models\Device;
use App\Models\Sensor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('shows the device battery_level on the device page', function () {
    $user = User::factory()->create();
    $device = Device::factory()->create([
        'user_id' => $user->id,
        'battery_level' => 88,
    ]);

    $response = $this->actingAs($user)->get(route('devices.show', $device));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Devices/Show')
        ->where('device.battery_level', 88)
    );
});

it('does not fall back to the battery sensor last_reading when device battery_level is null', function () {
    $user = User::factory()->create();
    $device = Device::factory()->create(['user_id' => $user->id, 'battery_level' => null]);
    Sensor::factory()->create([
        'device_id' => $device->id,
        'user_id' => $user->id,
        'type' => SensorType::BATTERY->value,
        'last_reading' => 52.5,
    ]);

    $response = $this->actingAs($user)->get(route('devices.show', $device));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Devices/Show')
        ->where('device.battery_level', null)
        ->missing('batteryReading')
    );
});
