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

    it('hides another user\'s device and does not provision it', function () {
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

    it('lists only the tenant devices with their farm, or none when unassigned', function () {
        $user = User::factory()->create();
        $farm = Farm::factory()->create(['user_id' => $user->id, 'name' => 'North Field']);
        Device::factory()->onFarm($farm)->create(['name' => 'A placed']);
        Device::factory()->create(['user_id' => $user->id, 'name' => 'B unassigned']);
        Device::factory()->create();

        $this->actingAs($user)
            ->get(route('devices.index'))
            ->assertInertia(fn ($page) => $page
                ->component('Devices/Index')
                ->has('devices', 2)
                ->where('devices.0.farm', ['id' => $farm->id, 'name' => 'North Field'])
                ->where('devices.1.farm', null)
                ->missing('devices.0.secret'));
    });

    it('requires authentication', function () {
        $this->get(route('devices.index'))->assertRedirect(route('login'));
    });
});

describe('DeviceController@show', function () {

    it('shows the device farm', function () {
        $user = User::factory()->create();
        $farm = Farm::factory()->create(['user_id' => $user->id, 'name' => 'North Field']);
        $device = Device::factory()->onFarm($farm)->create();

        $this->actingAs($user)
            ->get(route('devices.show', $device))
            ->assertInertia(fn ($page) => $page
                ->where('device.farm', ['id' => $farm->id, 'name' => 'North Field'])
                ->missing('farms'));
    });

    it('shows an unassigned device with no farm', function () {
        $user = User::factory()->create();
        $device = Device::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->get(route('devices.show', $device))
            ->assertInertia(fn ($page) => $page->where('device.farm', null));
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

    it('offers only the tenant farms and preselects the farm from the query string', function () {
        $user = User::factory()->create();
        $farm = Farm::factory()->create(['user_id' => $user->id, 'name' => 'North Field']);
        Farm::factory()->create();

        $this->actingAs($user)
            ->get(route('devices.create', ['farm' => $farm->id]))
            ->assertInertia(fn ($page) => $page
                ->component('Devices/Create')
                ->has('farms', 1)
                ->where('farms.0', ['id' => $farm->id, 'name' => 'North Field'])
                ->where('prefillFarmId', $farm->id));
    });

    it('does not preselect a farm of another tenant', function () {
        $otherFarm = Farm::factory()->create();

        $this->actingAs(User::factory()->create())
            ->get(route('devices.create', ['farm' => $otherFarm->id]))
            ->assertInertia(fn ($page) => $page->where('prefillFarmId', null));
    });
});

describe('DeviceController@edit', function () {

    it('shows the owner the device name, farm and the tenant farms', function () {
        $user = User::factory()->create();
        $farm = Farm::factory()->create(['user_id' => $user->id, 'name' => 'North Field']);
        $device = Device::factory()->onFarm($farm)->create(['name' => 'Node 7']);
        Farm::factory()->create();

        $this->actingAs($user)
            ->get(route('devices.edit', $device))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Devices/Edit')
                ->where('device.name', 'Node 7')
                ->where('device.farm_id', $farm->id)
                ->where('farms', [['id' => $farm->id, 'name' => 'North Field']])
                ->missing('device.secret')
                ->missing('device.lora_aes_key'));
    });

    it('is not found for another tenant', function () {
        $device = Device::factory()->create();

        $this->actingAs(User::factory()->create())
            ->get(route('devices.edit', $device))
            ->assertNotFound();
    });
});

describe('DeviceController@update', function () {

    it('lets the owner rename a device and move it to another farm', function () {
        $user = User::factory()->create();
        $device = Device::factory()->onFarm()->create(['user_id' => $user->id]);
        $newFarm = Farm::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->put(route('devices.update', $device), ['name' => 'Renamed node', 'farm_id' => $newFarm->id])
            ->assertRedirect(route('devices.show', $device))
            ->assertSessionHasNoErrors();

        expect($device->fresh())
            ->name->toBe('Renamed node')
            ->farm_id->toBe($newFarm->id);
    });

    it('lets the owner unassign a device without touching its sensor positions', function () {
        $user = User::factory()->create();
        $device = Device::factory()->onFarm()->create(['user_id' => $user->id]);
        $sensor = Sensor::factory()->create(['user_id' => $user->id, 'device_id' => $device->id, 'lat' => 37.5, 'lon' => 25.25]);
        expect(Farm::allTenants()->find($device->farm_id)->user_id)->toBe($user->id);

        $this->actingAs($user)
            ->put(route('devices.update', $device), ['name' => $device->name, 'farm_id' => ''])
            ->assertSessionHasNoErrors();

        expect($device->fresh()->farm_id)->toBeNull()
            ->and((float) $sensor->fresh()->lat)->toBe(37.5)
            ->and((float) $sensor->fresh()->lon)->toBe(25.25);
    });

    it('does not change the uuid, secret, type or LoRa key', function () {
        $user = User::factory()->create();
        $device = Device::factory()->lora()->create(['user_id' => $user->id]);
        $original = $device->only(['uuid', 'secret', 'type', 'lora_aes_key']);

        $this->actingAs($user)->put(route('devices.update', $device), [
            'name' => 'Node',
            'uuid' => 'new-uuid',
            'secret' => 'new-secret',
            'type' => 'wifi',
            'lora_aes_key' => str_repeat('a', 32),
        ]);

        expect($device->fresh()->only(['uuid', 'secret', 'type', 'lora_aes_key']))->toBe($original);
    });

    it('rejects a farm of another tenant', function () {
        $user = User::factory()->create();
        $device = Device::factory()->create(['user_id' => $user->id]);
        $otherFarm = Farm::factory()->create();

        $this->actingAs($user)
            ->put(route('devices.update', $device), ['name' => 'Node', 'farm_id' => $otherFarm->id])
            ->assertSessionHasErrors('farm_id');

        expect($device->fresh()->farm_id)->toBeNull();
    });

    it('is not found for another tenant', function () {
        $device = Device::factory()->create(['name' => 'Original']);

        $this->actingAs(User::factory()->create())
            ->put(route('devices.update', $device), ['name' => 'Hijacked'])
            ->assertNotFound();

        expect(Device::allTenants()->find($device->id)->name)->toBe('Original');
    });
});
