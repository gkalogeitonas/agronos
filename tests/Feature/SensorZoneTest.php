<?php

use App\Models\Device;
use App\Models\Farm;
use App\Models\Sensor;
use App\Models\User;
use App\Models\Zone;
use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\postJson;
use function Pest\Laravel\putJson;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->farm = Farm::factory()->create(['user_id' => $this->user->id]);
    $this->zone = Zone::factory()->for($this->farm)->create(['name' => 'Greenhouse']);
    $this->device = Device::factory()->onFarm($this->farm)->create();
    $this->unassignedDevice = Device::factory()->create(['user_id' => $this->user->id]);
    actingAs($this->user);
});

dataset('create routes', [
    'store' => 'sensors.store',
    'scan' => 'sensors.scan',
]);

it('creates a sensor in a zone of its device\'s farm', function (string $routeName) {
    postJson(route($routeName), [
        'device_uuid' => $this->device->uuid,
        'uuid' => 'sensor-in-zone',
        'type' => 'moisture',
        'zone_id' => $this->zone->id,
    ])->assertRedirect(route('sensors.index'));

    $this->assertDatabaseHas('sensors', ['uuid' => 'sensor-in-zone', 'zone_id' => $this->zone->id]);
})->with('create routes');

it('creates a sensor with no zone', function (string $routeName) {
    postJson(route($routeName), [
        'device_uuid' => $this->device->uuid,
        'uuid' => 'sensor-no-zone',
        'type' => 'moisture',
        'zone_id' => null,
    ])->assertRedirect(route('sensors.index'));

    $this->assertDatabaseHas('sensors', ['uuid' => 'sensor-no-zone', 'zone_id' => null]);
})->with('create routes');

dataset('rejected zones on create', [
    'another user\'s zone' => fn () => ['device_uuid' => $this->device->uuid, 'type' => 'moisture', 'zone_id' => Zone::factory()->create()->id],
    'a zone on another of the user\'s farms' => fn () => [
        'device_uuid' => $this->device->uuid,
        'type' => 'moisture',
        'zone_id' => Zone::factory()->for(Farm::factory()->create(['user_id' => $this->user->id]))->create()->id,
    ],
    'a zone that does not exist' => fn () => ['device_uuid' => $this->device->uuid, 'type' => 'moisture', 'zone_id' => 999999],
    'any zone for an unassigned device' => fn () => ['device_uuid' => $this->unassignedDevice->uuid, 'type' => 'moisture', 'zone_id' => $this->zone->id],
    'any zone on a diagnostic sensor' => fn () => ['device_uuid' => $this->device->uuid, 'type' => 'battery', 'zone_id' => $this->zone->id],
]);

it('rejects the zone on sensor create', function (string $routeName, array $payload) {
    postJson(route($routeName), $payload + ['uuid' => 'sensor-rejected-zone'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('zone_id');

    $this->assertDatabaseMissing('sensors', ['uuid' => 'sensor-rejected-zone']);
})->with('create routes')->with('rejected zones on create');

it('moves a sensor to a zone and back to no zone on update', function () {
    $sensor = Sensor::factory()->create(['user_id' => $this->user->id, 'device_id' => $this->device->id, 'type' => 'moisture']);

    putJson(route('sensors.update', $sensor), ['name' => 'Probe', 'zone_id' => $this->zone->id])
        ->assertRedirect(route('sensors.show', $sensor));
    expect($sensor->fresh()->zone_id)->toBe($this->zone->id);

    putJson(route('sensors.update', $sensor), ['name' => 'Probe', 'zone_id' => null])
        ->assertRedirect(route('sensors.show', $sensor));
    expect($sensor->fresh()->zone_id)->toBeNull();
});

it('rejects a zone that is not on the farm of the sensor\'s device on update', function (Zone $zone) {
    $sensor = Sensor::factory()->create(['user_id' => $this->user->id, 'device_id' => $this->device->id, 'type' => 'moisture']);

    putJson(route('sensors.update', $sensor), ['name' => 'Probe', 'zone_id' => $zone->id])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('zone_id');

    expect($sensor->fresh()->zone_id)->toBeNull();
})->with([
    'another user\'s zone' => fn () => Zone::factory()->create(),
    'a zone on another of the user\'s farms' => fn () => Zone::factory()->for(Farm::factory()->create(['user_id' => $this->user->id]))->create(),
]);

it('rejects any zone on update when the device is unassigned', function () {
    $sensor = Sensor::factory()->create(['user_id' => $this->user->id, 'device_id' => $this->unassignedDevice->id, 'type' => 'moisture']);

    putJson(route('sensors.update', $sensor), ['name' => 'Probe', 'zone_id' => $this->zone->id])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('zone_id');
});

it('rejects any zone on update of a diagnostic sensor', function () {
    $sensor = Sensor::factory()->create(['user_id' => $this->user->id, 'device_id' => $this->device->id, 'type' => 'battery']);

    putJson(route('sensors.update', $sensor), ['name' => 'Battery', 'zone_id' => $this->zone->id])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('zone_id');
});

it('forbids another user from setting the zone of a sensor', function () {
    $sensor = Sensor::factory()->create(['user_id' => $this->user->id, 'device_id' => $this->device->id, 'type' => 'moisture']);

    actingAs(User::factory()->create());
    putJson(route('sensors.update', $sensor), ['name' => 'Probe', 'zone_id' => $this->zone->id])->assertNotFound();

    expect($sensor->fresh()->zone_id)->toBeNull();
});

it('keeps the zone when an existing sensor is scanned again', function () {
    $otherZone = Zone::factory()->for($this->farm)->create();
    $sensor = Sensor::factory()->create([
        'user_id' => $this->user->id,
        'device_id' => $this->device->id,
        'type' => 'moisture',
        'zone_id' => $this->zone->id,
    ]);

    postJson(route('sensors.scan'), [
        'device_uuid' => $this->device->uuid,
        'uuid' => $sensor->uuid,
        'type' => 'moisture',
        'zone_id' => $otherZone->id,
    ])->assertRedirect(route('sensors.index'));

    expect($sensor->fresh()->zone_id)->toBe($this->zone->id);
});

it('gives the sensor create page each device\'s farm zones', function () {
    get(route('sensors.create'))->assertInertia(fn (AssertableInertia $page) => $page
        ->where('devices', fn ($devices) => collect($devices)->firstWhere('id', $this->device->id)['farm']['zones'] === [['id' => $this->zone->id, 'name' => 'Greenhouse']]
            && collect($devices)->firstWhere('id', $this->unassignedDevice->id)['farm'] === null));
});

it('gives the sensor edit page the zones of its device\'s farm and none for an unassigned device', function () {
    $sensor = Sensor::factory()->create(['user_id' => $this->user->id, 'device_id' => $this->device->id, 'zone_id' => $this->zone->id]);
    $unassignedSensor = Sensor::factory()->create(['user_id' => $this->user->id, 'device_id' => $this->unassignedDevice->id]);
    Zone::factory()->create();

    get(route('sensors.edit', $sensor))->assertInertia(fn (AssertableInertia $page) => $page
        ->where('sensor.zone_id', $this->zone->id)
        ->where('zones', [['id' => $this->zone->id, 'name' => 'Greenhouse']]));

    get(route('sensors.edit', $unassignedSensor))->assertInertia(fn (AssertableInertia $page) => $page
        ->where('zones', []));
});

it('shows each sensor\'s zone in the farm detail sensor list', function () {
    Sensor::factory()->create(['user_id' => $this->user->id, 'device_id' => $this->device->id, 'type' => 'moisture', 'name' => 'A in zone', 'zone_id' => $this->zone->id]);
    Sensor::factory()->create(['user_id' => $this->user->id, 'device_id' => $this->device->id, 'type' => 'moisture', 'name' => 'B no zone']);

    get(route('farms.show', $this->farm))->assertInertia(fn (AssertableInertia $page) => $page
        ->where('sensors.0.zone', ['id' => $this->zone->id, 'name' => 'Greenhouse'])
        ->where('sensors.1.zone', null));
});

it('shows the sensor\'s zone on the sensor page', function () {
    $sensor = Sensor::factory()->create(['user_id' => $this->user->id, 'device_id' => $this->device->id, 'zone_id' => $this->zone->id]);

    get(route('sensors.show', $sensor))->assertInertia(fn (AssertableInertia $page) => $page
        ->where('sensor.zone', ['id' => $this->zone->id, 'name' => 'Greenhouse']));
});
