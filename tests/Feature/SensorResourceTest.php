<?php

use App\Http\Requests\SensorRequest\SensorRequest;
use App\Models\Device;
use App\Models\Farm;
use App\Models\Sensor;
use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\delete;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\put;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->device = Device::factory(['user_id' => $this->user->id])->create();
    actingAs($this->user);
});

it('can list sensors', function () {
    Sensor::factory()->count(2)->create([
        'user_id' => $this->user->id,
        'device_id' => $this->device->id,
        'name' => 'Test Sensor',
    ]);
    $response = get('/sensors');
    $response->assertOk();
    $response->assertSee('Test Sensor');
});

it('can show a sensor', function () {
    $sensor = Sensor::factory()->create([
        'user_id' => $this->user->id,
        'device_id' => $this->device->id,
        'name' => 'Show Sensor',
    ]);
    $response = get('/sensors/'.$sensor->id);
    $response->assertOk();
    $response->assertSee('Show Sensor');
});

it('can create a sensor', function () {
    $payload = [
        'uuid' => 'test-create-uuid',
        'device_uuid' => $this->device->uuid,
        'user_id' => $this->user->id,
        'lat' => 1.23,
        'lon' => 4.56,
    ];
    $response = post('/sensors', $payload);
    $response->assertRedirect();
    $response->assertSessionHas('success', 'Sensor added.');
    $this->assertDatabaseHas('sensors', ['uuid' => 'test-create-uuid']);
});

it('can update a sensor', function () {
    $sensor = Sensor::factory()->create(['user_id' => $this->user->id, 'device_id' => $this->device->id]);
    $payload = [
        'lat' => 89.99,
        'lon' => 88.88,
    ];
    $response = put('/sensors/'.$sensor->id, $payload);
    $response->assertRedirect();
    $response->assertSessionHas('success', 'Sensor updated.');
    $this->assertDatabaseHas('sensors', ['id' => $sensor->id, 'lat' => 89.99, 'lon' => 88.88]);
});

it('can delete a sensor', function () {
    $sensor = Sensor::factory()->create(['user_id' => $this->user->id, 'device_id' => $this->device->id]);
    $response = delete('/sensors/'.$sensor->id);
    $response->assertRedirect();
    $response->assertSessionHas('success', 'Sensor deleted.');
    $this->assertDatabaseMissing('sensors', ['id' => $sensor->id]);
});

it('cannot list sensors belonging to another user', function () {
    $otherUser = User::factory()->create();
    Sensor::factory()->count(2)->create([
        'user_id' => $otherUser->id,
        'device_id' => $this->device->id,
        'name' => 'Other User Sensor',
    ]);
    $response = get('/sensors');
    $response->assertOk();
    $response->assertDontSee('Other User Sensor');
});

it('cannot show a sensor belonging to another user', function () {
    $otherUser = User::factory()->create();
    $sensor = Sensor::factory()->create([
        'user_id' => $otherUser->id,
        'device_id' => $this->device->id,
        'name' => 'Other User Sensor',
    ]);
    $response = get('/sensors/'.$sensor->id);
    $response->assertNotFound();
});

it('cannot update a sensor belonging to another user', function () {
    $otherUser = User::factory()->create();
    $sensor = Sensor::factory()->create([
        'user_id' => $otherUser->id,
        'device_id' => $this->device->id,
        'name' => 'Other User Sensor',
    ]);
    $payload = [
        'lat' => 55.55,
        'lon' => 44.44,
    ];
    $response = put('/sensors/'.$sensor->id, $payload);
    $response->assertNotFound();
    $this->assertDatabaseMissing('sensors', ['id' => $sensor->id, 'lat' => 55.55, 'lon' => 44.44]);
});

it('cannot create a sensor without device_uuid', function () {
    $payload = [
        'uuid' => 'no-device-id',
        'user_id' => $this->user->id,
        'lat' => 1.23,
        'lon' => 4.56,
    ];
    $response = post('/sensors', $payload);
    $response->assertSessionHasErrors('device_uuid');
    $this->assertDatabaseMissing('sensors', ['uuid' => 'no-device-id']);
});

it('cannot create a sensor with non-existing device_uuid', function () {
    $payload = [
        'uuid' => 'bad-device-id',
        'device_uuid' => 'not-a-real-uuid',
        'user_id' => $this->user->id,
        'lat' => 1.23,
        'lon' => 4.56,
    ];
    $response = post('/sensors', $payload);
    $response->assertSessionHasErrors('device_uuid');
    $this->assertDatabaseMissing('sensors', ['uuid' => 'bad-device-id']);
});

it('cannot create a sensor with a device_uuid that does not belong to the user', function () {
    $otherUser = User::factory()->create();
    $otherDevice = Device::factory(['user_id' => $otherUser->id])->create();
    $payload = [
        'uuid' => 'wrong-owner-device',
        'device_uuid' => $otherDevice->uuid,
        'user_id' => $this->user->id,
        'lat' => 1.23,
        'lon' => 4.56,
    ];
    $response = post('/sensors', $payload);
    $response->assertSessionHasErrors(['device_uuid' => SensorRequest::UNREGISTERED_DEVICE_MESSAGE]);
    $this->assertDatabaseMissing('sensors', ['uuid' => 'wrong-owner-device']);
});

it('ignores a farm sent with a new sensor', function () {
    $farm = Farm::factory(['user_id' => $this->user->id])->create();
    $payload = [
        'uuid' => 'sensor-with-farm',
        'device_uuid' => $this->device->uuid,
        'user_id' => $this->user->id,
        'lat' => 12.34,
        'lon' => 56.78,
        'farm_id' => $farm->id,
    ];
    $response = post('/sensors', $payload);
    $response->assertRedirect();
    $response->assertSessionHas('success', 'Sensor added.');
    $this->assertDatabaseHas('sensors', ['uuid' => 'sensor-with-farm']);
});

it('shows the farm of the sensor\'s device', function () {
    $farm = Farm::factory(['user_id' => $this->user->id, 'name' => 'North field'])->create();
    $device = Device::factory()->onFarm($farm)->create();
    $sensor = Sensor::factory()->create(['user_id' => $this->user->id, 'device_id' => $device->id]);

    get(route('sensors.show', $sensor))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Sensors/Show')
            ->where('sensor.farm.id', $farm->id)
            ->where('sensor.farm.name', 'North field')
            ->has('sensor.farm.coordinates'));
});

it('shows no farm for a sensor whose device is unassigned', function () {
    $sensor = Sensor::factory()->create([
        'user_id' => $this->user->id,
        'device_id' => $this->device->id,
    ]);

    get(route('sensors.show', $sensor))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Sensors/Show')
            ->where('sensor.farm', null));
});

it('lists each sensor with the farm of its device', function () {
    $farm = Farm::factory(['user_id' => $this->user->id])->create();
    $device = Device::factory()->onFarm($farm)->create();
    Sensor::factory()->create(['user_id' => $this->user->id, 'device_id' => $device->id, 'name' => 'A placed']);
    Sensor::factory()->create([
        'user_id' => $this->user->id,
        'device_id' => $this->device->id,
        'name' => 'B unassigned',
    ]);

    get(route('sensors.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Sensors/Index')
            ->where('sensors.0.device.farm_id', $farm->id)
            ->where('sensors.1.device.farm_id', null)
            ->where('farms.0.id', $farm->id));
});

it('filters the sensors on the server', function (array $query, array $expectedNames) {
    $farm = Farm::factory(['user_id' => $this->user->id])->create();
    $placedDevice = Device::factory()->onFarm($farm)->create(['status' => 'online']);
    $this->device->update(['status' => 'offline']);
    Sensor::factory()->create(['user_id' => $this->user->id, 'device_id' => $placedDevice->id, 'name' => 'Placed probe', 'type' => 'moisture']);
    Sensor::factory()->create(['user_id' => $this->user->id, 'device_id' => $this->device->id, 'name' => 'Loose probe', 'type' => 'light']);
    $foreignDevice = Device::factory()->onFarm()->create(['status' => 'online']);
    Sensor::factory()->create(['user_id' => $foreignDevice->user_id, 'device_id' => $foreignDevice->id, 'name' => 'Foreign probe', 'type' => 'moisture']);

    $query = array_map(fn ($value) => match ($value) {
        'FARM' => $farm->id,
        'FOREIGN' => $foreignDevice->farm_id,
        default => $value,
    }, $query);

    get(route('sensors.index', $query))
        ->assertInertia(fn ($page) => $page
            ->where('sensors', fn ($sensors) => collect($sensors)->pluck('name')->all() === $expectedNames)
            ->where('filters.farm', isset($query['farm']) ? (string) $query['farm'] : 'all'));
})->with([
    'type' => [['type' => 'light'], ['Loose probe']],
    'farm' => [['farm' => 'FARM'], ['Placed probe']],
    'unassigned farm' => [['farm' => 'unassigned'], ['Loose probe']],
    'device status' => [['status' => 'online'], ['Placed probe']],
    'another tenant\'s farm' => [['farm' => 'FOREIGN'], []],
]);

it('orders the sensors by their group, with unassigned last, and counts each whole group', function () {
    $southFarm = Farm::factory(['user_id' => $this->user->id])->create(['name' => 'South']);
    $northFarm = Farm::factory(['user_id' => $this->user->id])->create(['name' => 'North']);
    Sensor::factory()->create(['user_id' => $this->user->id, 'device_id' => $this->device->id, 'name' => 'A loose', 'type' => 'humidity']);
    Sensor::factory()->create(['user_id' => $this->user->id, 'device_id' => Device::factory()->onFarm($southFarm), 'name' => 'B south', 'type' => 'temperature']);
    Sensor::factory()->count(50)->sequence(fn ($sequence) => ['name' => sprintf('C north %02d', $sequence->index)])
        ->create(['user_id' => $this->user->id, 'device_id' => Device::factory()->onFarm($northFarm), 'type' => 'moisture']);

    get(route('sensors.index', ['page' => 2]))
        ->assertInertia(fn ($page) => $page
            ->where('sensors', fn ($sensors) => collect($sensors)->pluck('name')->all() === ['B south', 'A loose'])
            ->where('groupCounts', [(string) $northFarm->id => 50, (string) $southFarm->id => 1, 'unassigned' => 1]));

    get(route('sensors.index', ['group' => 'type']))
        ->assertInertia(fn ($page) => $page
            ->where('sensors.0.name', 'A loose')
            ->where('sensors.1.name', 'C north 00')
            ->where('groupCounts', ['humidity' => 1, 'moisture' => 50, 'temperature' => 1]));
});

it('counts and offers sensor filters across all the tenant sensors, not the current page', function () {
    $farm = Farm::factory(['user_id' => $this->user->id])->create(['name' => 'North Field']);
    Sensor::factory()->count(51)->create(['user_id' => $this->user->id, 'device_id' => Device::factory()->onFarm($farm), 'type' => 'moisture']);
    Sensor::factory()->create(['user_id' => $this->user->id, 'device_id' => $this->device->id, 'type' => 'light']);
    Sensor::factory()->count(2)->create(['type' => 'humidity']);

    get(route('sensors.index', ['type' => 'moisture']))
        ->assertInertia(fn ($page) => $page
            ->has('sensors', 50)
            ->where('pagination.total', 51)
            ->where('typeCounts', ['all' => 52, 'light' => 1, 'moisture' => 51])
            ->where('farms', [['id' => $farm->id, 'name' => 'North Field']])
            ->where('hasUnassigned', true));
});
