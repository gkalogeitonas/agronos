<?php

use App\Models\Device;
use App\Models\Sensor;
use App\Models\User;
use App\Enums\SensorType;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('uses device battery_level when present', function () {
    $user = User::factory()->create();
    $device = Device::factory()->create([
        'user_id' => $user->id,
        'battery_level' => 88,
    ]);

    // also create a battery sensor with a different last_reading to ensure device value wins
    Sensor::factory()->create([
        'device_id' => $device->id,
        'user_id' => $user->id,
        'type' => SensorType::BATTERY->value,
        'last_reading' => 42,
    ]);

    $response = $this->actingAs($user)->get(route('devices.show', $device));

    $response->assertStatus(200);
    $response->assertInertia(fn ($page) => $page
        ->component('Devices/Show')
        ->has('batteryReading')
        ->where('batteryReading', 88)
    );
});

it('falls back to battery sensor last_reading when device battery_level is null', function () {
    $user = User::factory()->create();
    $device = Device::factory()->create(['user_id' => $user->id, 'battery_level' => null]);

    $battery = Sensor::factory()->create([
        'device_id' => $device->id,
        'user_id' => $user->id,
        'type' => SensorType::BATTERY->value,
        'last_reading' => 52.5,
    ]);

    $response = $this->actingAs($user)->get(route('devices.show', $device));

    $response->assertStatus(200);
    $response->assertInertia(fn ($page) => $page
        ->component('Devices/Show')
        ->has('batteryReading')
        ->where('batteryReading', 52.5)
    );
});
