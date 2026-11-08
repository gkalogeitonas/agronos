<?php

use App\Models\Device;
use App\Models\Farm;
use App\Models\Sensor;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\postJson;
use function Pest\Laravel\putJson;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->deviceFarm = Farm::factory()->create(['user_id' => $this->user->id]);
    $this->device = Device::factory()->onFarm($this->deviceFarm)->create();
    actingAs($this->user);
});

dataset('sent farms', [
    'the tenant\'s own farm' => fn () => Farm::factory()->create(['user_id' => $this->user->id]),
    'another tenant\'s farm' => fn () => Farm::factory()->create(),
]);

it('puts a new sensor on its device\'s farm whatever farm is sent', function (Farm $sentFarm) {
    postJson(route('sensors.store'), [
        'device_uuid' => $this->device->uuid,
        'uuid' => 'sensor-sent-farm',
        'type' => 'moisture',
        'farm_id' => $sentFarm->id,
    ])->assertRedirect(route('sensors.index'));

    $this->assertDatabaseHas('sensors', ['uuid' => 'sensor-sent-farm']);
    get(route('farms.show', $this->deviceFarm))->assertInertia(fn (AssertableInertia $page) => $page
        ->has('sensors', 1)
        ->where('sensors.0.uuid', 'sensor-sent-farm'));
})->with('sent farms');

it('leaves the sensor on its device\'s farm when an edit sends a farm', function (Farm $sentFarm) {
    $sensor = Sensor::factory()->create(['user_id' => $this->user->id, 'device_id' => $this->device->id, 'type' => 'moisture']);

    putJson(route('sensors.update', $sensor), ['name' => 'Renamed', 'farm_id' => $sentFarm->id])
        ->assertRedirect(route('sensors.show', $sensor));

    $this->assertDatabaseHas('sensors', ['id' => $sensor->id, 'name' => 'Renamed']);
    get(route('farms.show', $this->deviceFarm))->assertInertia(fn (AssertableInertia $page) => $page
        ->has('sensors', 1)
        ->where('sensors.0.name', 'Renamed'));
})->with('sent farms');

it('puts a scanned new sensor on its device\'s farm whatever farm is sent', function (Farm $sentFarm) {
    postJson(route('sensors.scan'), [
        'device_uuid' => $this->device->uuid,
        'uuid' => 'sensor-scan-sent-farm',
        'type' => 'moisture',
        'farm_id' => $sentFarm->id,
    ])->assertRedirect(route('sensors.index'));

    $this->assertDatabaseHas('sensors', ['uuid' => 'sensor-scan-sent-farm']);
    get(route('farms.show', $this->deviceFarm))->assertInertia(fn (AssertableInertia $page) => $page
        ->has('sensors', 1)
        ->where('sensors.0.uuid', 'sensor-scan-sent-farm'));
})->with('sent farms');

it('changes no farm when an existing sensor is scanned again with a farm', function (Farm $sentFarm) {
    $sensor = Sensor::factory()->create(['user_id' => $this->user->id, 'device_id' => $this->device->id]);

    postJson(route('sensors.scan'), [
        'device_uuid' => $this->device->uuid,
        'uuid' => $sensor->uuid,
        'farm_id' => $sentFarm->id,
    ])->assertRedirect(route('sensors.index'));

    $this->assertDatabaseHas('devices', ['id' => $this->device->id, 'farm_id' => $this->deviceFarm->id]);
})->with('sent farms');

it('gives the sensor create page the tenant\'s devices with their farm and boundary', function () {
    $this->deviceFarm->update(['name' => 'North Orchard', 'coordinates' => orchardBoundary()]);
    $unassignedDevice = Device::factory()->create(['user_id' => $this->user->id, 'name' => 'Z unassigned']);
    $this->device->update(['name' => 'A placed']);
    Device::factory()->onFarm()->create();

    get(route('sensors.create'))->assertInertia(fn (AssertableInertia $page) => $page
        ->component('Sensors/Create')
        ->where('selectedFarm', null)
        ->where('prefillDeviceUuid', null)
        ->where('devices', [
            [
                'id' => $this->device->id,
                'name' => 'A placed',
                'uuid' => $this->device->uuid,
                'farm' => ['id' => $this->deviceFarm->id, 'name' => 'North Orchard', 'coordinates' => orchardBoundary(), 'zones' => []],
            ],
            ['id' => $unassignedDevice->id, 'name' => 'Z unassigned', 'uuid' => $unassignedDevice->uuid, 'farm' => null],
        ]));
});

it('gives the farm-context sensor create page the farm and only the tenant\'s devices', function () {
    $this->deviceFarm->update(['name' => 'North Orchard', 'coordinates' => orchardBoundary()]);
    Device::factory()->onFarm()->create();

    get(route('farms.sensors.create', $this->deviceFarm))->assertInertia(fn (AssertableInertia $page) => $page
        ->component('Sensors/Create')
        ->where('selectedFarm', ['id' => $this->deviceFarm->id, 'name' => 'North Orchard', 'coordinates' => orchardBoundary()])
        ->has('devices', 1)
        ->where('devices.0.id', $this->device->id));
});

it('returns 404 for the sensor create page of another tenant\'s farm', function () {
    get(route('farms.sensors.create', Farm::factory()->create()))->assertNotFound();
});

it('preselects the device from the URL on the sensor create page', function () {
    get(route('sensors.create', ['device' => $this->device->id]))->assertInertia(fn (AssertableInertia $page) => $page
        ->where('prefillDeviceUuid', $this->device->uuid));
});

it('does not preselect another tenant\'s device on the sensor create page', function () {
    $otherDevice = Device::factory()->create();

    get(route('sensors.create', ['device' => $otherDevice->id]))->assertInertia(fn (AssertableInertia $page) => $page
        ->where('prefillDeviceUuid', null));
});

it('gives the sensor edit page the boundary of its device\'s farm', function () {
    $this->deviceFarm->update(['coordinates' => orchardBoundary()]);
    $sensor = Sensor::factory()->create(['user_id' => $this->user->id, 'device_id' => $this->device->id]);

    get(route('sensors.edit', $sensor))->assertInertia(fn (AssertableInertia $page) => $page
        ->component('Sensors/Edit')
        ->missing('farms')
        ->where('sensor.device.uuid', $this->device->uuid)
        ->where('sensor.device.farm.coordinates', orchardBoundary()));
});

it('gives the sensor edit page no boundary when the device is unassigned', function () {
    $this->device->update(['farm_id' => null]);
    $sensor = Sensor::factory()->create(['user_id' => $this->user->id, 'device_id' => $this->device->id]);

    get(route('sensors.edit', $sensor))->assertInertia(fn (AssertableInertia $page) => $page
        ->where('sensor.device.farm', null));
});

it('returns 404 for the sensor edit page of another tenant\'s sensor', function () {
    get(route('sensors.edit', Sensor::factory()->create()))->assertNotFound();
});

/**
 * @return array{type: string, coordinates: array<int, array<int, array<int, float>>>}
 */
function orchardBoundary(): array
{
    return [
        'type' => 'Polygon',
        'coordinates' => [[[25.1, 37.1], [25.2, 37.1], [25.2, 37.2], [25.1, 37.1]]],
    ];
}
