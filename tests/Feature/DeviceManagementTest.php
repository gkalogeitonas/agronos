<?php

use App\Models\Device;
use App\Models\Farm;
use App\Models\Sensor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->devices = Device::factory()->count(3)->create(['user_id' => $this->user->id]);
});

test('authenticated user can view device index', function () {
    $response = $this
        ->actingAs($this->user)
        ->get(route('devices.index'));

    $response->assertStatus(200);
    foreach ($this->devices as $device) {
        $response->assertSee($device->name);
    }
});

test('authenticated user can view a specific device', function () {
    $device = $this->devices->first();

    $response = $this
        ->actingAs($this->user)
        ->get(route('devices.show', $device));

    $response->assertStatus(200);
    $response->assertSee($device->name);
    $response->assertSee($device->uuid);
});

test('authenticated user can delete a device', function () {
    $device = $this->devices->first();

    $response = $this
        ->actingAs($this->user)
        ->delete(route('devices.destroy', $device));

    $response->assertRedirect(route('devices.index'));
    $response->assertSessionHas('success', 'Device deleted.');
    $this->assertDatabaseMissing('devices', ['id' => $device->id]);
});

test('guest cannot access device index', function () {
    $response = $this->get(route('devices.index'));
    $response->assertRedirect(route('login'));
});

test('device index lists each device with its farms and without credentials', function () {
    $farm = Farm::factory()->create(['user_id' => $this->user->id, 'name' => 'North Orchard']);
    $device = $this->devices->first();
    $device->update(['mqtt_username' => 'device-user', 'mqtt_password' => 'device-pass']);
    Sensor::factory()->create(['user_id' => $this->user->id, 'device_id' => $device->id, 'farm_id' => $farm->id]);

    $this->actingAs($this->user)
        ->get(route('devices.index'))
        ->assertInertia(function ($page) use ($device) {
            $page->component('Devices/Index')->has('devices', 3);
            $row = collect($page->toArray()['props']['devices'])->firstWhere('id', $device->id);

            expect($row['farms'])->toBe(['North Orchard'])
                ->and($row)->not->toHaveKeys(['mqtt_username', 'mqtt_password', 'secret']);
        });
});
