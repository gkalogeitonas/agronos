<?php

use App\Enums\AlertSeverity;
use App\Enums\DeviceStatus;
use App\Models\Alert;
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
    $online = Device::factory()->onFarm($farm)->create(['status' => DeviceStatus::ONLINE, 'battery_level' => 80]);
    $offline = Device::factory()->onFarm($farm)->create(['status' => DeviceStatus::OFFLINE, 'battery_level' => 10]);
    Device::factory()->onFarm($farm)->create(['status' => DeviceStatus::ONLINE, 'battery_level' => 90]);
    Device::factory()->create(['user_id' => $user->id, 'status' => DeviceStatus::OFFLINE, 'battery_level' => 90]);

    Sensor::factory()->create(['user_id' => $user->id, 'device_id' => $online->id, 'type' => 'moisture', 'last_reading' => 40]);
    Sensor::factory()->create(['user_id' => $user->id, 'device_id' => $offline->id, 'type' => 'moisture', 'last_reading' => 30]);

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertInertia(fn ($page) => $page->component('Dashboard')
            ->where('fleet.farms', 1)
            ->where('fleet.devicesTotal', 4)
            ->where('fleet.devicesOnline', 2)
            ->where('fleet.lowBattery', 1)
            ->where('farms.0.name', 'North Orchard')
            ->where('farms.0.health', 'warning')
            ->where('farms.0.healthLabel', 'Warning')
            ->where('farms.0.devicesOnline', 2)
            ->where('farms.0.devicesTotal', 3)
            ->where('farms.0.moisture', 35)
        );
});

test('dashboard shares the configured low battery threshold with the frontend', function () {
    config(['agronos.low_battery_percent' => 30]);

    $this->actingAs(User::factory()->create())
        ->get('/dashboard')
        ->assertInertia(fn ($page) => $page->where('lowBatteryPercent', 30));
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

test('dashboard lists the open alerts and counts them on the tile', function () {
    $user = User::factory()->create();
    $farm = Farm::factory()->create(['user_id' => $user->id, 'name' => 'North Orchard']);
    $device = Device::factory()->offline()->onFarm($farm)->create(['name' => 'Pump shed']);
    $alert = Alert::factory()->forDevice($device)->create(['opened_at' => '2026-11-05 12:00:00']);

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertInertia(fn ($page) => $page
            ->where('fleet.openAlerts', 1)
            ->has('alerts', 1)
            ->where('alerts.0', [
                'id' => $alert->id,
                'title' => 'Device offline',
                'severity' => 'danger',
                'device' => 'Pump shed',
                'farm' => 'North Orchard',
                'href' => route('devices.show', $device),
                'opened_at' => '2026-11-05T12:00:00.000000Z',
            ])
        );
});

test('dashboard lists five alerts, danger first then newest, and still counts them all', function () {
    $user = User::factory()->create();
    $devices = Device::factory()->offline()->count(6)->create(['user_id' => $user->id]);
    $olderDanger = Alert::factory()->forDevice($devices[0])->create(['opened_at' => '2026-11-01 08:00:00']);
    $tiedDangerFirst = Alert::factory()->forDevice($devices[1])->create(['opened_at' => '2026-11-02 08:00:00']);
    $tiedDangerSecond = Alert::factory()->forDevice($devices[2])->create(['opened_at' => '2026-11-02 08:00:00']);
    $olderWarning = Alert::factory()->forDevice($devices[3])->create(['severity' => AlertSeverity::WARNING, 'opened_at' => '2026-11-03 08:00:00']);
    $newerWarning = Alert::factory()->forDevice($devices[4])->create(['severity' => AlertSeverity::WARNING, 'opened_at' => '2026-11-04 08:00:00']);
    Alert::factory()->forDevice($devices[5])->create(['severity' => AlertSeverity::INFO, 'opened_at' => '2026-11-05 08:00:00']);

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertInertia(fn ($page) => $page
            ->where('fleet.openAlerts', 6)
            ->where('alerts', fn ($alerts) => $alerts->pluck('id')->all() === [
                $tiedDangerSecond->id,
                $tiedDangerFirst->id,
                $olderDanger->id,
                $newerWarning->id,
                $olderWarning->id,
            ])
        );
});

test('dashboard neither lists nor counts resolved alerts', function () {
    $user = User::factory()->create();
    Alert::factory()->forDevice(Device::factory()->offline()->create(['user_id' => $user->id]))->resolved()->create();

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertInertia(fn ($page) => $page
            ->where('fleet.openAlerts', 0)
            ->has('alerts', 0)
        );
});

test('dashboard neither lists nor counts the alerts of another user', function () {
    Alert::factory()->create();

    $this->actingAs(User::factory()->create())
        ->get('/dashboard')
        ->assertInertia(fn ($page) => $page
            ->where('fleet.openAlerts', 0)
            ->has('alerts', 0)
        );
});
