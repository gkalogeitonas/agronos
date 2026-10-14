<?php

use App\Models\Device;
use App\Models\Farm;
use App\Models\Sensor;
use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;

beforeEach(function () {
    $this->user = User::factory()->create();
    actingAs($this->user);
});

it('shows each farm on the overview with its status and device count', function () {
    $farm = Farm::factory()->create(['user_id' => $this->user->id, 'name' => 'North Orchard']);
    $device = Device::factory()->create(['user_id' => $this->user->id, 'status' => 'online']);
    Sensor::factory()->create(['user_id' => $this->user->id, 'farm_id' => $farm->id, 'device_id' => $device->id]);

    $page = visit(route('dashboard', absolute: false))
        ->assertSee('North Orchard')
        ->assertSeeIn('article span.rounded-full', 'OK')
        ->assertNoJavaScriptErrors();

    expect($page->text('article dl.grid'))->toContain('1 / 1');
});

it('saves edits to a farm and shows them on its page', function () {
    $farm = Farm::factory()->create(['user_id' => $this->user->id, 'name' => 'North Orchard']);

    visit(route('farms.edit', $farm, absolute: false))
        ->fill('#farm-name', 'South Orchard')
        ->fill('#farm-location', 'Naxos')
        ->press('Save changes')
        ->assertPathIs(route('farms.show', $farm, absolute: false))
        ->assertSee('South Orchard');

    expect($farm->refresh())
        ->name->toBe('South Orchard')
        ->location->toBe('Naxos');
});

it('shows the server validation error when the location is blank', function () {
    $farm = Farm::factory()->create(['user_id' => $this->user->id, 'location' => 'Naxos']);

    visit(route('farms.edit', $farm, absolute: false))
        ->fill('#farm-location', '   ')
        ->press('Save changes')
        ->assertSee('The location field is required.')
        ->assertPathIs(route('farms.edit', $farm, absolute: false));

    expect($farm->refresh()->location)->toBe('Naxos');
});

it('creates a farm from a drawn boundary and calculates its area', function () {
    $page = visit(route('farms.create', absolute: false))
        ->fill('#farm-name', 'Hilltop Greenhouse')
        ->fill('#farm-location', 'Paros');

    // Mapbox Draw starts in polygon mode once the map has loaded; click four corners, then the first corner again to close the shape.
    $page->script(<<<'JS'
        async () => {
            while (!document.querySelector('.mapbox-gl-draw_polygon')) {
                await new Promise((resolve) => setTimeout(resolve, 100));
            }
            const canvas = document.querySelector('.mapboxgl-canvas');
            const box = canvas.getBoundingClientRect();
            [[0.3, 0.3], [0.7, 0.3], [0.7, 0.7], [0.3, 0.7], [0.3, 0.3]].forEach(([x, y]) => {
                const init = { bubbles: true, clientX: box.left + box.width * x, clientY: box.top + box.height * y };
                ['mousemove', 'mousedown', 'mouseup', 'click'].forEach((type) => canvas.dispatchEvent(new MouseEvent(type, init)));
            });
        }
        JS);

    $page->assertSee('m²')
        ->assertDontSee('No boundary yet')
        ->press('Create farm')
        ->assertPathIs(route('farms.index', absolute: false))
        ->assertSee('Hilltop Greenhouse');

    $farm = Farm::where('name', 'Hilltop Greenhouse')->firstOrFail();

    expect($farm->user_id)->toBe($this->user->id)
        ->and((float) $farm->size)->toBeGreaterThan(0)
        ->and($farm->coordinates['type'])->toBe('Polygon');
})->group('maps');

it('creates a farm without a drawn boundary', function () {
    visit(route('farms.create', absolute: false))
        ->fill('Name', 'Hilltop')
        ->fill('Location', 'Crete')
        ->press('Create farm')
        ->assertSee('Hilltop')
        ->assertNoJavaScriptErrors();

    assertDatabaseHas('farms', ['name' => 'Hilltop', 'size' => null]);
});

it('keeps the farm when the delete confirmation is cancelled', function () {
    $farm = Farm::factory()->create(['user_id' => $this->user->id, 'name' => 'North Orchard']);

    visit(route('farms.show', $farm, absolute: false))
        ->click('Delete')
        ->assertSee('Delete North Orchard?')
        ->press('Cancel')
        ->assertDontSee('Delete North Orchard?')
        ->assertPathIs(route('farms.show', $farm, absolute: false));

    $this->assertDatabaseHas('farms', ['id' => $farm->id]);
});

it('deletes the farm once the confirmation is accepted', function () {
    $farm = Farm::factory()->create(['user_id' => $this->user->id, 'name' => 'North Orchard']);

    visit(route('farms.show', $farm, absolute: false))
        ->click('Delete')
        ->assertSee('Delete North Orchard?')
        ->press('[role="dialog"] button:has-text("Delete")')
        ->assertPathIs(route('farms.index', absolute: false));

    $this->assertDatabaseMissing('farms', ['id' => $farm->id]);
});
