<?php

use App\Models\Crop;
use App\Models\Device;
use App\Models\Farm;
use App\Models\Planting;
use App\Models\User;
use App\Models\Zone;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;

beforeEach(function () {
    $this->user = User::factory()->create();
    actingAs($this->user);
});

it('shows each farm on the overview with its status and device count', function () {
    $farm = Farm::factory()->create(['user_id' => $this->user->id, 'name' => 'North Orchard']);
    Device::factory()->onFarm($farm)->create(['status' => 'online']);

    $page = visit(route('dashboard', absolute: false))
        ->assertSee('North Orchard')
        ->assertSeeIn('article span.rounded-full', 'OK')
        ->assertNoJavaScriptErrors();

    expect($page->text('article dl.grid'))->toContain('1 / 1');
});

it('opens device registration with the farm preselected from the farm page', function () {
    $farm = Farm::factory()->create(['user_id' => $this->user->id, 'name' => 'North Orchard']);

    visit(route('farms.show', $farm, absolute: false))
        ->click('header >> text=Add device')
        ->assertPathIs(route('devices.create', absolute: false))
        ->assertSelected('#farm_id', $farm->id)
        ->assertNoJavaScriptErrors();
});

it('points an empty farm to adding a device before its sensors', function () {
    $farm = Farm::factory()->create(['user_id' => $this->user->id]);

    visit(route('farms.show', $farm, absolute: false))
        ->assertSeeIn('section[aria-label="Sensors"]', 'Add device')
        ->assertDontSeeIn('section[aria-label="Sensors"]', 'Add sensor')
        ->assertNoJavaScriptErrors();
});

it('offers adding a sensor once the farm has a device', function () {
    $farm = Farm::factory()->create(['user_id' => $this->user->id]);
    Device::factory()->onFarm($farm)->create();

    visit(route('farms.show', $farm, absolute: false))
        ->click('section[aria-label="Sensors"] >> text=Add sensor')
        ->assertPathIs(route('farms.sensors.create', $farm, absolute: false))
        ->assertNoJavaScriptErrors();
});

it('links from the farm page to all of its devices', function () {
    $farm = Farm::factory()->create(['user_id' => $this->user->id]);
    Device::factory()->onFarm($farm)->create(['name' => 'Farm node']);
    Device::factory()->create(['user_id' => $this->user->id, 'name' => 'Loose node']);

    visit(route('farms.show', $farm, absolute: false))
        ->click('section[aria-label="Devices"] >> text=View all')
        ->assertPathIs(route('devices.index', absolute: false))
        ->assertQueryStringHas('farm', (string) $farm->id)
        ->assertSee('Farm node')
        ->assertDontSee('Loose node')
        ->assertNoJavaScriptErrors();
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

it('adds a zone without a name as Zone 1 from the farm page', function () {
    $farm = Farm::factory()->create(['user_id' => $this->user->id]);

    visit(route('farms.show', $farm, absolute: false))
        ->click('section[aria-label="Zones"] >> text=Add zone')
        ->assertAttribute('#zone-name', 'placeholder', 'Zone 1')
        ->press('[role="dialog"] button[type="submit"]')
        ->assertSeeIn('section[aria-label="Zones"] ul', 'Zone 1')
        ->assertNoJavaScriptErrors();

    assertDatabaseHas('zones', ['farm_id' => $farm->id, 'name' => 'Zone 1']);
});

it('offers to end the current crop when adding the next one', function () {
    $farm = Farm::factory()->create(['user_id' => $this->user->id]);
    $olive = Crop::query()->where('name', 'Olive')->sole();
    $wheat = Crop::query()->where('name', 'Wheat')->sole();
    $current = Planting::factory()->for($farm)->create(['crop_id' => $olive->id, 'started_on' => '2024-01-01']);

    visit(route('farms.show', $farm, absolute: false))
        ->click('section[aria-label="Crops"] >> text=Add crop')
        ->select('#planting-crop', (string) $wheat->id)
        ->fill('#planting-start', '2026-05-01')
        ->press('[role="dialog"] button[type="submit"]')
        ->assertSeeIn('[role="dialog"]', 'Olive (2024-01-01 to now) is still growing. End it on 2026-05-01 to add this crop?')
        ->press('[role="dialog"] button:has-text("End it and add")')
        ->assertSeeIn('section[aria-label="Crops"] ul', 'Wheat')
        ->assertSeeIn('section[aria-label="Crops"] ul', '2024-01-01 to 2026-05-01')
        ->assertNoJavaScriptErrors();

    expect($current->fresh()->ended_on->toDateString())->toBe('2026-05-01');
});

it('adds a crop missing from the list and selects it in the planting dialog', function () {
    $farm = Farm::factory()->create(['user_id' => $this->user->id]);

    $page = visit(route('farms.show', $farm, absolute: false))
        ->click('section[aria-label="Crops"] >> text=Add crop')
        ->select('#planting-crop', 'add')
        ->fill('#new-crop-name', 'Saffron')
        ->press('Add to my crops')
        ->assertDontSee('New crop name');

    $saffron = Crop::query()->where('name', 'Saffron')->sole();

    $page->assertSelected('#planting-crop', (string) $saffron->id)
        ->press('[role="dialog"] button[type="submit"]')
        ->assertSeeIn('section[aria-label="Crops"] ul', 'Saffron')
        ->assertNoJavaScriptErrors();

    expect($saffron->user_id)->toBe($this->user->id);
    assertDatabaseHas('plantings', ['farm_id' => $farm->id, 'crop_id' => $saffron->id]);
});

it('plants a crop in one zone, which then shows it over the farm\'s crop', function () {
    $farm = Farm::factory()->create(['user_id' => $this->user->id]);
    $north = Zone::factory()->for($farm)->create(['name' => 'North']);
    Zone::factory()->for($farm)->create(['name' => 'South']);
    $olive = Crop::query()->where('name', 'Olive')->sole();
    $wheat = Crop::query()->where('name', 'Wheat')->sole();
    Planting::factory()->for($farm)->create(['crop_id' => $olive->id]);

    visit(route('farms.show', $farm, absolute: false))
        ->assertSeeIn('section[aria-label="Zones"] ul li:has-text("North")', "Olive (farm's crop)")
        ->click('section[aria-label="Crops"] >> text=Add crop')
        ->select('#planting-land', (string) $north->id)
        ->select('#planting-crop', (string) $wheat->id)
        ->press('[role="dialog"] button[type="submit"]')
        ->assertSeeIn('section[aria-label="Crops"] ul', 'North · Current')
        ->assertSeeIn('section[aria-label="Zones"] ul li:has-text("North")', 'Wheat')
        ->assertSeeIn('section[aria-label="Zones"] ul li:has-text("South")', "Olive (farm's crop)")
        ->assertNoJavaScriptErrors();

    assertDatabaseHas('plantings', ['farm_id' => $farm->id, 'zone_id' => $north->id, 'crop_id' => $wheat->id]);
});
