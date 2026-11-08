<?php

use App\Models\Device;
use App\Models\Farm;
use App\Models\Sensor;
use App\Models\User;
use App\Models\Zone;
use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\assertDatabaseMissing;
use function Pest\Laravel\delete;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\put;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->farm = Farm::factory()->create(['user_id' => $this->user->id]);
    actingAs($this->user);
});

describe('adding a zone', function () {
    it('adds a zone with the given name', function () {
        post(route('farms.zones.store', $this->farm), ['name' => 'Greenhouse'])
            ->assertRedirect(route('farms.show', $this->farm));

        assertDatabaseHas('zones', ['farm_id' => $this->farm->id, 'user_id' => $this->user->id, 'name' => 'Greenhouse']);
    });

    it('names zones without a name Zone 1, then Zone 2', function () {
        post(route('farms.zones.store', $this->farm), ['name' => '']);
        post(route('farms.zones.store', $this->farm), []);

        expect($this->farm->zones()->orderBy('id')->pluck('name')->all())->toBe(['Zone 1', 'Zone 2']);
    });

    it('fills the gap a deleted zone left first', function () {
        foreach (['Zone 1', 'Zone 2', 'Zone 3'] as $name) {
            Zone::factory()->for($this->farm)->create(['name' => $name]);
        }
        $this->farm->zones()->where('name', 'Zone 2')->delete();

        post(route('farms.zones.store', $this->farm), ['name' => '']);

        expect($this->farm->zones()->pluck('name')->sort()->values()->all())->toBe(['Zone 1', 'Zone 2', 'Zone 3']);
    });

    it('rejects a name another zone on the farm already has', function () {
        Zone::factory()->for($this->farm)->create(['name' => 'Greenhouse']);

        post(route('farms.zones.store', $this->farm), ['name' => 'Greenhouse'])
            ->assertSessionHasErrors(['name' => 'This farm already has a zone with this name.']);

        expect($this->farm->zones()->count())->toBe(1);
    });

    it('allows a name a zone on another farm already has', function () {
        $otherFarm = Farm::factory()->create(['user_id' => $this->user->id]);
        Zone::factory()->for($otherFarm)->create(['name' => 'Greenhouse']);

        post(route('farms.zones.store', $this->farm), ['name' => 'Greenhouse'])->assertSessionHasNoErrors();

        assertDatabaseHas('zones', ['farm_id' => $this->farm->id, 'name' => 'Greenhouse']);
    });

    it('does not add a zone to another user\'s farm', function () {
        $otherFarm = Farm::factory()->create();

        post(route('farms.zones.store', $otherFarm), ['name' => 'Greenhouse'])->assertNotFound();

        assertDatabaseMissing('zones', ['farm_id' => $otherFarm->id]);
    });
});

describe('renaming a zone', function () {
    it('renames the zone', function () {
        $zone = Zone::factory()->for($this->farm)->create(['name' => 'Zone 1']);

        put(route('farms.zones.update', [$this->farm, $zone]), ['name' => 'Greenhouse'])
            ->assertRedirect(route('farms.show', $this->farm));

        expect($zone->refresh()->name)->toBe('Greenhouse');
    });

    it('gives the zone a new automatic name when the name is cleared', function () {
        Zone::factory()->for($this->farm)->create(['name' => 'Zone 1']);
        $zone = Zone::factory()->for($this->farm)->create(['name' => 'Greenhouse']);

        put(route('farms.zones.update', [$this->farm, $zone]), ['name' => '']);

        expect($zone->refresh()->name)->toBe('Zone 2');
    });

    it('keeps the name when it is saved unchanged', function () {
        $zone = Zone::factory()->for($this->farm)->create(['name' => 'Greenhouse']);

        put(route('farms.zones.update', [$this->farm, $zone]), ['name' => 'Greenhouse'])->assertSessionHasNoErrors();

        expect($zone->refresh()->name)->toBe('Greenhouse');
    });

    it('rejects a name another zone on the farm already has', function () {
        Zone::factory()->for($this->farm)->create(['name' => 'Greenhouse']);
        $zone = Zone::factory()->for($this->farm)->create(['name' => 'Orchard']);

        put(route('farms.zones.update', [$this->farm, $zone]), ['name' => 'Greenhouse'])
            ->assertSessionHasErrors(['name' => 'This farm already has a zone with this name.']);

        expect($zone->refresh()->name)->toBe('Orchard');
    });

    it('does not rename another user\'s zone', function () {
        $otherZone = Zone::factory()->create(['name' => 'Orchard']);

        put(route('farms.zones.update', [$otherZone->farm_id, $otherZone]), ['name' => 'Mine'])->assertNotFound();

        expect(Zone::allTenants()->find($otherZone->id)->name)->toBe('Orchard');
    });

    it('does not reach a zone through another farm', function () {
        $otherFarm = Farm::factory()->create(['user_id' => $this->user->id]);
        $zone = Zone::factory()->for($otherFarm)->create(['name' => 'Orchard']);

        put(route('farms.zones.update', [$this->farm, $zone]), ['name' => 'Moved'])->assertNotFound();

        expect($zone->refresh())->farm_id->toBe($otherFarm->id)->name->toBe('Orchard');
    });
});

describe('deleting a zone', function () {
    it('deletes the zone and leaves its sensors on the farm with no zone', function () {
        $zone = Zone::factory()->for($this->farm)->create();
        $device = Device::factory()->onFarm($this->farm)->create();
        $sensor = Sensor::factory()->for($device)->create(['user_id' => $this->user->id, 'zone_id' => $zone->id]);

        delete(route('farms.zones.destroy', [$this->farm, $zone]))
            ->assertRedirect(route('farms.show', $this->farm));

        assertDatabaseMissing('zones', ['id' => $zone->id]);
        expect($sensor->refresh()->zone_id)->toBeNull()
            ->and($this->farm->sensors()->pluck('sensors.id')->all())->toBe([$sensor->id]);
    });

    it('does not delete another user\'s zone', function () {
        $otherZone = Zone::factory()->create();

        delete(route('farms.zones.destroy', [$otherZone->farm_id, $otherZone]))->assertNotFound();

        expect(Zone::allTenants()->find($otherZone->id))->not->toBeNull();
    });
});

it('deletes the zones of a deleted farm', function () {
    $zone = Zone::factory()->for($this->farm)->create();

    delete(route('farms.destroy', $this->farm));

    assertDatabaseMissing('zones', ['id' => $zone->id]);
});

it('lists the farm\'s zones on its page with their sensor counts and the next automatic name', function () {
    $zone = Zone::factory()->for($this->farm)->create(['name' => 'Zone 1']);
    Zone::factory()->for($this->farm)->create(['name' => 'Greenhouse']);
    Zone::factory()->create(['name' => 'Someone else\'s']);
    $device = Device::factory()->onFarm($this->farm)->create();
    Sensor::factory()->count(2)->for($device)->create(['user_id' => $this->user->id, 'zone_id' => $zone->id]);

    get(route('farms.show', $this->farm))->assertInertia(fn (AssertableInertia $page) => $page
        ->has('zones', 2)
        ->where('zones.0', ['id' => $zone->id, 'name' => 'Zone 1', 'sensors_count' => 2])
        ->where('zones.1.name', 'Greenhouse')
        ->where('zones.1.sensors_count', 0)
        ->where('nextZoneName', 'Zone 2'));
});

it('lets only the owner rename or delete a zone', function (string $ability) {
    $zone = Zone::factory()->for($this->farm)->create();

    expect($this->user->can($ability, $zone))->toBeTrue()
        ->and(User::factory()->create()->can($ability, $zone))->toBeFalse();
})->with(['update', 'delete']);
