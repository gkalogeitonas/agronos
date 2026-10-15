<?php

use App\Models\Device;
use App\Models\Farm;
use App\Models\Sensor;
use App\Models\User;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->user = User::factory()->create();
    actingAs($this->user);

    $this->farm = Farm::factory()->create(['user_id' => $this->user->id]);
    $this->device = Device::factory()->create(['user_id' => $this->user->id]);
    $this->sensor = Sensor::factory()->create([
        'user_id' => $this->user->id,
        'farm_id' => $this->farm->id,
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
        ->select('#farm_id', (string) $this->farm->id)
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
        ->select('#farm_id', (string) $this->farm->id)
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
