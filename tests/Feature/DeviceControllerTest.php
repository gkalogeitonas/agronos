<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\Farm;
use App\Models\Sensor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

describe('DeviceController@createMqttCredentials', function () {

    it('creates mqtt credentials for a device', function () {
        $user = User::factory()->create();
        $device = Device::factory()->create(['user_id' => $user->id]);

        fakeEmqxProvisioning($device->uuid);

        $response = $this->actingAs($user)
            ->post(route('devices.mqtt.create', $device));

        $response->assertRedirect(route('devices.show', $device));
        $response->assertSessionHas('mqtt_credentials', function ($credentials) {
            return $credentials['created'] === true
                && $credentials['username'] !== null
                && $credentials['password'] !== null;
        });

        $device->refresh();
        expect($device->mqtt_username)->toBe($device->uuid);
        expect($device->mqtt_password)->not->toBeNull();
        // assert also database has the updated credentials
        $this->assertDatabaseHas('devices', [
            'id' => $device->id,
            'mqtt_username' => $device->uuid,
            'mqtt_password' => $device->mqtt_password,
        ]);
        expect(emqxRulesWritten($device->uuid, "agronos/devices/{$device->uuid}"))->toBeTrue();
    });

    it('returns existing credentials and ensures their broker user and rules', function () {
        $user = User::factory()->create();
        $device = Device::factory()->create([
            'user_id' => $user->id,
            'mqtt_username' => 'existing-user',
            'mqtt_password' => 'existing-pass',
        ]);

        fakeEmqxProvisioning('existing-user', userStatus: 409);

        $response = $this->actingAs($user)
            ->post(route('devices.mqtt.create', $device));

        $response->assertSessionHas('mqtt_credentials', function ($credentials) {
            return $credentials['created'] === false
                && $credentials['username'] === 'existing-user'
                && $credentials['password'] === 'existing-pass';
        });
        expect($device->refresh()->mqtt_password)->toBe('existing-pass');
        expect(emqxRulesWritten('existing-user', "agronos/devices/{$device->uuid}"))->toBeTrue();
    });

    it('flashes error when emqx returns error response', function () {
        $user = User::factory()->create();
        $device = Device::factory()->create(['user_id' => $user->id]);

        fakeEmqxProvisioning($device->uuid, userStatus: 400);

        $response = $this->actingAs($user)
            ->post(route('devices.mqtt.create', $device));

        $response->assertSessionHas('mqtt_credentials', function ($credentials) {
            return $credentials['created'] === false
                && $credentials['error'] === 'emqx_unavailable';
        });

        $device->refresh();
        expect($device->mqtt_username)->toBeNull();
        expect($device->mqtt_password)->toBeNull();
    });

    it('forbids provisioning another user\'s device', function () {
        $device = Device::factory()->create();
        Http::preventStrayRequests();

        $this->actingAs(User::factory()->create())
            ->post(route('devices.mqtt.create', $device))
            ->assertNotFound();

        Http::assertNothingSent();
    });

    it('requires authentication', function () {
        $device = Device::factory()->create();

        $response = $this->post(route('devices.mqtt.create', $device));

        $response->assertRedirect(route('login'));
    });
});

describe('DeviceController@index', function () {

    it('lists only the tenant devices with their farm names', function () {
        $user = User::factory()->create();
        $farm = Farm::factory()->create(['user_id' => $user->id, 'name' => 'North Field']);
        $device = Device::factory()->create(['user_id' => $user->id]);
        Sensor::factory()->count(2)->create(['user_id' => $user->id, 'device_id' => $device->id, 'farm_id' => $farm->id]);
        Device::factory()->create();

        $this->actingAs($user)
            ->get(route('devices.index'))
            ->assertInertia(fn ($page) => $page
                ->component('Devices/Index')
                ->has('devices', 1)
                ->where('devices.0.farms', ['North Field'])
                ->missing('devices.0.secret'));
    });

    it('requires authentication', function () {
        $this->get(route('devices.index'))->assertRedirect(route('login'));
    });
});

describe('DeviceController@create', function () {

    it('prefills the device uuid from the query string', function () {
        $this->actingAs(User::factory()->create())
            ->get(route('devices.create', ['uuid' => 'scanned-device-uuid']))
            ->assertInertia(fn ($page) => $page
                ->component('Devices/Create')
                ->where('prefillUuid', 'scanned-device-uuid'));
    });
});
