<?php

use App\Models\Device;
use App\Models\Farm;
use App\Models\Sensor;
use App\Models\User;
use App\Models\Zone;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->user = User::factory()->create();
    actingAs($this->user);

    $this->farm = Farm::factory()->create(['user_id' => $this->user->id]);
    $this->device = Device::factory()->create(['user_id' => $this->user->id]);
    $this->sensor = Sensor::factory()->create([
        'user_id' => $this->user->id,
        'device_id' => $this->device->id,
        'name' => 'Zone A probe',
        'type' => 'moisture',
        'lat' => 37.0582268,
        'lon' => 25.4074122,
    ]);
});

it('keeps the sensor when the delete confirmation is cancelled', function () {
    visit(route('sensors.show', $this->sensor, absolute: false))
        ->click('Delete')
        ->assertSee('Delete Zone A probe?')
        ->press('Cancel')
        ->assertDontSee('Delete Zone A probe?')
        ->assertPathIs(route('sensors.show', $this->sensor, absolute: false));

    $this->assertDatabaseHas('sensors', ['id' => $this->sensor->id]);
});

it('deletes the sensor once the confirmation is accepted', function () {
    visit(route('sensors.show', $this->sensor, absolute: false))
        ->click('Delete')
        ->press('[role="dialog"] button:has-text("Delete")')
        ->assertPathIs(route('sensors.index', absolute: false));

    $this->assertDatabaseMissing('sensors', ['id' => $this->sensor->id]);
});

it('saves coordinates typed into the sensor form', function () {
    visit(route('sensors.edit', $this->sensor, absolute: false))
        ->fill('#lat', '37.0585')
        ->fill('#lon', '25.4080')
        ->press('Save changes')
        ->assertPathIs(route('sensors.show', $this->sensor, absolute: false));

    expect($this->sensor->refresh())
        ->lat->toEqual(37.0585)
        ->lon->toEqual(25.408);
});

it('fills the coordinates from the browser location on the add sensor page', function () {
    visit(route('sensors.create', absolute: false))
        ->geolocation(37.1, 25.5)
        ->wait(1)
        ->assertValue('#lat', '37.1')
        ->assertValue('#lon', '25.5');
});

it('moves the sensor to the clicked point on the map', function () {
    $page = visit(route('sensors.edit', $this->sensor, absolute: false));

    // The marker sits at the centre of the map, so click away from it.
    $page->script(<<<'JS'
        async () => {
            while (!document.querySelector('.mapboxgl-marker')) {
                await new Promise((resolve) => setTimeout(resolve, 100));
            }
            const canvas = document.querySelector('.mapboxgl-canvas');
            const box = canvas.getBoundingClientRect();
            const init = { bubbles: true, clientX: box.left + box.width * 0.2, clientY: box.top + box.height * 0.3 };
            ['mousemove', 'mousedown', 'mouseup', 'click'].forEach((type) => canvas.dispatchEvent(new MouseEvent(type, init)));
        }
        JS);

    $page->assertValueIsNot('#lat', '37.0582268')
        ->assertValueIsNot('#lon', '25.4074122');
})->group('maps');

it('asks before a typed uuid updates an existing sensor and confirms the update with a toast', function () {
    $page = visit(route('sensors.create', absolute: false))
        ->click('#allow-edit-switch')
        ->fill('#name', 'Typed name')
        ->select('#type', 'moisture')
        ->fill('#uuid', $this->sensor->uuid)
        ->fill('#device_uuid', $this->device->uuid)
        ->fill('#lat', '37.1')
        ->fill('#lon', '25.5')
        ->press('button[type="submit"]')
        ->assertSee('This UUID belongs to an existing sensor "Zone A probe". Confirm to update it, or check the UUID.')
        ->assertPathIs(route('sensors.create', absolute: false));

    expect($this->sensor->refresh()->name)->toBe('Zone A probe');

    $page->press('Update existing sensor')
        ->assertPathIs(route('sensors.index', absolute: false))
        ->assertSee('Sensor updated.')
        ->assertNoJavaScriptErrors();

    expect($this->sensor->refresh()->name)->toBe('Typed name');
});

it('shows a toast after adding a sensor with a new uuid', function () {
    visit(route('sensors.create', absolute: false))
        ->click('#allow-edit-switch')
        ->fill('#name', 'Zone B probe')
        ->select('#type', 'moisture')
        ->fill('#uuid', 'typed-new-uuid')
        ->fill('#device_uuid', $this->device->uuid)
        ->fill('#lat', '37.1')
        ->fill('#lon', '25.5')
        ->press('button[type="submit"]')
        ->assertPathIs(route('sensors.index', absolute: false))
        ->assertSee('Sensor added.')
        ->assertNoJavaScriptErrors();

    $this->assertDatabaseHas('sensors', ['uuid' => 'typed-new-uuid', 'name' => 'Zone B probe']);
});

it('does not repeat a toast when going back to the page that showed it', function () {
    $page = visit(route('sensors.edit', $this->sensor, absolute: false))
        ->press('Save changes')
        ->assertPathIs(route('sensors.show', $this->sensor, absolute: false))
        ->assertSee('Sensor updated.')
        ->press('[aria-label="Dismiss"]')
        ->assertDontSee('Sensor updated.')
        ->click('Edit')
        ->assertPathIs(route('sensors.edit', $this->sensor, absolute: false))
        ->back()
        ->assertPathIs(route('sensors.show', $this->sensor, absolute: false))
        ->wait(1)
        ->assertDontSee('Sensor updated.');
});

it('groups and filters the sensor list by the device\'s farm', function () {
    $farm = Farm::factory()->create(['user_id' => $this->user->id, 'name' => 'North Field']);
    $placedDevice = Device::factory()->onFarm($farm)->create();
    Sensor::factory()->create(['user_id' => $this->user->id, 'device_id' => $placedDevice->id, 'name' => 'North probe']);

    visit(route('sensors.index', absolute: false))
        ->assertSee('North Field')
        ->assertSee('Unassigned')
        ->assertSee('North probe')
        ->assertSee('Zone A probe')
        ->select('#farm-filter', 'unassigned')
        ->assertQueryStringHas('farm', 'unassigned')
        ->assertSee('Zone A probe')
        ->assertDontSee('North probe')
        ->select('#farm-filter', (string) $farm->id)
        ->assertQueryStringHas('farm', (string) $farm->id)
        ->assertSee('North probe')
        ->assertDontSee('Zone A probe')
        ->assertNoJavaScriptErrors();
});

it('tells the user when the device entered from a farm page is on another farm', function () {
    $otherFarm = Farm::factory()->create(['user_id' => $this->user->id, 'name' => 'North Orchard']);
    $otherDevice = Device::factory()->onFarm($otherFarm)->create();
    $placedDevice = Device::factory()->onFarm($this->farm)->create();

    visit(route('farms.sensors.create', $this->farm, absolute: false))
        ->click('#allow-edit-switch')
        ->fill('#device_uuid', $placedDevice->uuid)
        ->assertDontSee('so the sensor will appear there')
        ->fill('#device_uuid', $otherDevice->uuid)
        ->assertSee('This device is on North Orchard, so the sensor will appear there.')
        ->assertPresent('a[href$="'.route('devices.edit', $otherDevice, absolute: false).'"]')
        ->assertNoJavaScriptErrors();
});

it('tells the user when the device entered from a farm page is unassigned', function () {
    $this->farm->update(['name' => 'South Field']);

    visit(route('farms.sensors.create', $this->farm, absolute: false))
        ->click('#allow-edit-switch')
        ->fill('#device_uuid', $this->device->uuid)
        ->assertSee('This device is unassigned, so the sensor will not appear on South Field.')
        ->assertPresent('a[href$="'.route('devices.edit', $this->device, absolute: false).'"]')
        ->assertNoJavaScriptErrors();
});

it('saves the sensor despite the notice, on the device\'s farm', function () {
    $otherFarm = Farm::factory()->create(['user_id' => $this->user->id, 'name' => 'North Orchard']);
    $otherDevice = Device::factory()->onFarm($otherFarm)->create();

    visit(route('farms.sensors.create', $this->farm, absolute: false))
        ->click('#allow-edit-switch')
        ->select('#type', 'moisture')
        ->fill('#uuid', 'noticed-uuid')
        ->fill('#device_uuid', $otherDevice->uuid)
        ->assertSee('so the sensor will appear there')
        ->fill('#lat', '37.1')
        ->fill('#lon', '25.5')
        ->press('button[type="submit"]')
        ->assertPathIs(route('sensors.index', absolute: false))
        ->assertNoJavaScriptErrors();

    $this->assertDatabaseHas('sensors', ['uuid' => 'noticed-uuid', 'device_id' => $otherDevice->id]);
});

it('adds a sensor without a position', function () {
    visit(route('sensors.create', absolute: false))
        ->click('#allow-edit-switch')
        ->select('#type', 'moisture')
        ->fill('#uuid', 'no-position-uuid')
        ->fill('#device_uuid', $this->device->uuid)
        ->press('button[type="submit"]')
        ->assertPathIs(route('sensors.index', absolute: false))
        ->assertNoJavaScriptErrors();

    $this->assertDatabaseHas('sensors', ['uuid' => 'no-position-uuid', 'lat' => null, 'lon' => null]);
});

it('shows no notice for a device that is not recognised, and no boundary', function () {
    $this->farm->update(['coordinates' => squareBoundary(25.4)]);

    $page = visit(route('farms.sensors.create', $this->farm, absolute: false));
    waitForBoundary($page, squareBoundary(25.4));

    $page->click('#allow-edit-switch')->fill('#device_uuid', 'not-a-device');
    waitForNoBoundary($page);

    $page->assertDontSee('This device is')->assertNoJavaScriptErrors();
})->group('maps');

it('shows the boundary of the entered device\'s farm in the location picker', function () {
    $this->farm->update(['coordinates' => squareBoundary(25.4)]);
    $otherFarm = Farm::factory()->create(['user_id' => $this->user->id, 'coordinates' => squareBoundary(26.1)]);
    $otherDevice = Device::factory()->onFarm($otherFarm)->create();

    $page = visit(route('farms.sensors.create', $this->farm, absolute: false));
    waitForBoundary($page, squareBoundary(25.4));

    $page->click('#allow-edit-switch')->fill('#device_uuid', $otherDevice->uuid);
    waitForBoundary($page, squareBoundary(26.1));

    $page->fill('#device_uuid', $this->device->uuid);
    waitForNoBoundary($page);

    $page->assertNoJavaScriptErrors();
})->group('maps');

it('shows the boundary of the device\'s farm on the sensor edit page', function () {
    $farm = Farm::factory()->create(['user_id' => $this->user->id, 'coordinates' => squareBoundary(25.4)]);
    $this->device->update(['farm_id' => $farm->id]);

    $page = visit(route('sensors.edit', $this->sensor, absolute: false));

    waitForBoundary($page, squareBoundary(25.4));
    $page->assertNoJavaScriptErrors();
})->group('maps');

/**
 * @return array{type: string, coordinates: array<int, array<int, array<int, float>>>}
 */
function squareBoundary(float $lng): array
{
    return [
        'type' => 'Polygon',
        'coordinates' => [[[$lng, 37.05], [$lng + 0.01, 37.05], [$lng + 0.01, 37.06], [$lng, 37.06], [$lng, 37.05]]],
    ];
}

/**
 * @param  array{type: string, coordinates: array<int, array<int, array<int, float>>>}  $boundary
 */
function waitForBoundary(mixed $page, array $boundary): void
{
    $expected = json_encode(json_encode($boundary['coordinates']));

    $page->script(<<<JS
        async () => {
            while (document.querySelector('[data-boundary]')?.dataset.boundary !== {$expected}) {
                await new Promise((resolve) => setTimeout(resolve, 100));
            }
        }
        JS);
}

function waitForNoBoundary(mixed $page): void
{
    $page->script(<<<'JS'
        async () => {
            while (document.querySelector('[data-boundary]')) {
                await new Promise((resolve) => setTimeout(resolve, 100));
            }
        }
        JS);
}

it('adds a sensor into a zone of its device\'s farm', function () {
    Zone::factory()->for($this->farm)->create(['name' => 'Greenhouse']);
    Zone::factory()->for(Farm::factory()->create(['user_id' => $this->user->id]))->create(['name' => 'Elsewhere']);
    $placedDevice = Device::factory()->onFarm($this->farm)->create();

    visit(route('farms.sensors.create', $this->farm, absolute: false))
        ->click('#allow-edit-switch')
        ->select('#type', 'moisture')
        ->fill('#uuid', 'zoned-uuid')
        ->fill('#device_uuid', $this->device->uuid)
        ->assertSee('This device is unassigned. Put it on a farm to choose a zone.')
        ->assertAttribute('#zone_id', 'disabled', '')
        ->fill('#device_uuid', $placedDevice->uuid)
        ->assertDontSee('Put it on a farm to choose a zone.')
        ->assertCount('#zone_id option', 2)
        ->assertPresent('#zone_id option:has-text("Greenhouse")')
        ->select('#zone_id', 'Greenhouse')
        ->select('#type', 'battery')
        ->assertNotPresent('#zone_id')
        ->select('#type', 'moisture')
        ->select('#zone_id', 'Greenhouse')
        ->press('button[type="submit"]')
        ->assertPathIs(route('sensors.index', absolute: false))
        ->assertNoJavaScriptErrors();

    $this->assertDatabaseHas('sensors', ['uuid' => 'zoned-uuid', 'zone_id' => Zone::where('name', 'Greenhouse')->value('id')]);
});

it('moves a sensor to a zone on the edit page and shows it on the sensor page', function () {
    $this->device->update(['farm_id' => $this->farm->id]);
    Zone::factory()->for($this->farm)->create(['name' => 'Greenhouse']);

    visit(route('sensors.edit', $this->sensor, absolute: false))
        ->select('#zone_id', 'Greenhouse')
        ->press('Save changes')
        ->assertPathIs(route('sensors.show', $this->sensor, absolute: false))
        ->assertSee('Greenhouse')
        ->assertNoJavaScriptErrors();
});
