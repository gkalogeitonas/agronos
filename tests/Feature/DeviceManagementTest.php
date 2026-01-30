<?php

use App\Models\Device;
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
    $this->assertDatabaseMissing('devices', ['id' => $device->id]);
});

test('guest cannot access device index', function () {
    $response = $this->get(route('devices.index'));
    $response->assertRedirect(route('login'));
});
