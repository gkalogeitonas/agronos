<?php

use App\Enums\DeviceStatus;
use App\Models\Device;
use App\Models\Farm;
use App\Models\Sensor;
use App\Models\User;

test('guests are redirected to the login page', function () {
    $response = $this->get('/dashboard');
    $response->assertRedirect('/login');
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get('/dashboard');
    $response->assertStatus(200);
});

test('dashboard summarises the fleet and each farm', function () {
    $user = User::factory()->create();
    $farm = Farm::factory()->create(['user_id' => $user->id, 'name' => 'North Orchard']);
    $online = Device::factory()->create(['user_id' => $user->id, 'status' => DeviceStatus::ONLINE, 'battery_level' => 80]);
    $offline = Device::factory()->create(['user_id' => $user->id, 'status' => DeviceStatus::OFFLINE, 'battery_level' => 10]);

    Sensor::factory()->create(['user_id' => $user->id, 'farm_id' => $farm->id, 'device_id' => $online->id, 'type' => 'moisture', 'last_reading' => 40]);
    Sensor::factory()->create(['user_id' => $user->id, 'farm_id' => $farm->id, 'device_id' => $offline->id, 'type' => 'moisture', 'last_reading' => 30]);

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertInertia(fn ($page) => $page->component('Dashboard')
            ->where('fleet.farms', 1)
            ->where('fleet.devicesTotal', 2)
            ->where('fleet.devicesOnline', 1)
            ->where('fleet.lowBattery', 1)
            ->where('farms.0.name', 'North Orchard')
            ->where('farms.0.health', 'warning')
            ->where('farms.0.healthLabel', 'Warning')
            ->where('farms.0.devicesOnline', 1)
            ->where('farms.0.devicesTotal', 2)
            ->where('farms.0.moisture', 35)
        );
});

test('dashboard does not include other users farms or devices', function () {
    $user = User::factory()->create();
    Farm::factory()->create();
    Device::factory()->create();

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertInertia(fn ($page) => $page
            ->where('fleet.farms', 0)
            ->where('fleet.devicesTotal', 0)
            ->has('farms', 0)
        );
});
