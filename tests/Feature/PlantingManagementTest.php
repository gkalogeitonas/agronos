<?php

use App\Models\Crop;
use App\Models\Farm;
use App\Models\Planting;
use App\Models\User;
use App\Models\Zone;
use Database\Seeders\CropCatalogueSeeder;
use Illuminate\Database\QueryException;
use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\assertDatabaseMissing;
use function Pest\Laravel\delete;
use function Pest\Laravel\get;
use function Pest\Laravel\patch;
use function Pest\Laravel\post;
use function Pest\Laravel\put;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->farm = Farm::factory()->create(['user_id' => $this->user->id]);
    $this->olive = Crop::query()->where('name', 'Olive')->sole();
    actingAs($this->user);
});

describe('the crop catalogue', function () {
    it('is loaded by the migrations with the named crops', function () {
        $names = Crop::query()->whereNull('user_id')->pluck('name');

        expect($names)->toContain('Wine grape', 'Table grape', 'Fallow', 'Vegetable garden', 'Mixed orchard')
            ->and($names->count())->toBeBetween(24, 60);
    });

    it('can be seeded again without duplicates', function () {
        $before = Crop::query()->count();

        (new CropCatalogueSeeder)->run();
        (new CropCatalogueSeeder)->run();

        expect(Crop::query()->count())->toBe($before)
            ->and(Crop::query()->where('name', 'Olive')->count())->toBe(1);
    });

    it('shows a user the catalogue and their own crops, not another user\'s', function () {
        $mine = Crop::factory()->forTenant($this->user)->create();
        $theirs = Crop::factory()->forTenant()->create();

        $ids = Crop::query()->pluck('id');

        expect($ids)->toContain($mine->id, $this->olive->id)->not->toContain($theirs->id);
    });

    it('cannot delete a crop that a planting uses', function () {
        Planting::factory()->for($this->farm)->create(['crop_id' => $this->olive->id]);

        expect(fn () => $this->olive->delete())->toThrow(QueryException::class);
    });
});

describe('listing plantings on the farm page', function () {
    it('lists whole-farm plantings, current first, then upcoming, then past', function () {
        $past = Planting::factory()->for($this->farm)->ended()->create();
        $upcoming = Planting::factory()->for($this->farm)->future()->create();
        $current = Planting::factory()->for($this->farm)->create();
        Planting::factory()->for($this->farm)->forZone()->create();

        get(route('farms.show', $this->farm))->assertInertia(fn (AssertableInertia $page) => $page
            ->has('plantings', 3)
            ->where('plantings.0.id', $current->id)
            ->where('plantings.0.status', 'current')
            ->where('plantings.1.id', $upcoming->id)
            ->where('plantings.1.status', 'upcoming')
            ->where('plantings.2.id', $past->id)
            ->where('plantings.2.status', 'past')
            ->has('crops')
        );
    });

    it('offers the catalogue and own crops but not another user\'s', function () {
        $theirs = Crop::factory()->forTenant()->create();

        get(route('farms.show', $this->farm))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('crops', fn ($crops) => ! collect($crops)->pluck('id')->contains($theirs->id) && collect($crops)->pluck('id')->contains($this->olive->id))
        );
    });

    it('does not show another user\'s farm', function () {
        $otherFarm = Farm::factory()->create();

        get(route('farms.show', $otherFarm))->assertNotFound();
    });
});

describe('adding a planting', function () {
    it('adds a whole-farm planting with a catalogue crop, variety and dates', function () {
        post(route('farms.plantings.store', $this->farm), [
            'crop_id' => $this->olive->id,
            'variety' => 'Koroneiki',
            'started_on' => '2024-03-01',
            'ended_on' => '2030-01-01',
        ])->assertRedirect(route('farms.show', $this->farm));

        assertDatabaseHas('plantings', [
            'farm_id' => $this->farm->id,
            'user_id' => $this->user->id,
            'zone_id' => null,
            'crop_id' => $this->olive->id,
            'variety' => 'Koroneiki',
        ]);
    });

    it('needs only a crop', function () {
        post(route('farms.plantings.store', $this->farm), ['crop_id' => $this->olive->id])->assertSessionHasNoErrors();

        assertDatabaseHas('plantings', ['farm_id' => $this->farm->id, 'started_on' => null, 'ended_on' => null, 'variety' => null]);
    });

    it('accepts a crop the user added', function () {
        $mine = Crop::factory()->forTenant($this->user)->create();

        post(route('farms.plantings.store', $this->farm), ['crop_id' => $mine->id])->assertSessionHasNoErrors();

        assertDatabaseHas('plantings', ['farm_id' => $this->farm->id, 'crop_id' => $mine->id]);
    });

    it('requires a crop', function () {
        post(route('farms.plantings.store', $this->farm), [])->assertSessionHasErrors('crop_id');
    });

    it('rejects a crop another user added', function () {
        $theirs = Crop::factory()->forTenant()->create();

        post(route('farms.plantings.store', $this->farm), ['crop_id' => $theirs->id])->assertSessionHasErrors('crop_id');

        expect($this->farm->plantings()->count())->toBe(0);
    });

    it('rejects a crop that does not exist', function () {
        post(route('farms.plantings.store', $this->farm), ['crop_id' => 999999])->assertSessionHasErrors('crop_id');
    });

    it('rejects an end date before the start date', function () {
        post(route('farms.plantings.store', $this->farm), [
            'crop_id' => $this->olive->id,
            'started_on' => '2024-03-01',
            'ended_on' => '2024-02-28',
        ])->assertSessionHasErrors(['ended_on' => 'The end date cannot be before the start date.']);
    });

    it('allows a future planting, which is not current', function () {
        $start = now()->addMonths(2)->toDateString();

        post(route('farms.plantings.store', $this->farm), ['crop_id' => $this->olive->id, 'started_on' => $start])
            ->assertSessionHasNoErrors();

        get(route('farms.show', $this->farm))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('plantings.0.status', 'upcoming'));
    });

    it('puts the planting on the farm in the route, ignoring a farm id in the form', function () {
        $otherFarm = Farm::factory()->create(['user_id' => $this->user->id]);

        post(route('farms.plantings.store', $this->farm), ['crop_id' => $this->olive->id, 'farm_id' => $otherFarm->id, 'zone_id' => Zone::factory()->for($otherFarm)->create()->id]);

        assertDatabaseHas('plantings', ['farm_id' => $this->farm->id, 'zone_id' => null]);
        assertDatabaseMissing('plantings', ['farm_id' => $otherFarm->id]);
    });

    it('does not add a planting to another user\'s farm', function () {
        $otherFarm = Farm::factory()->create();

        post(route('farms.plantings.store', $otherFarm), ['crop_id' => $this->olive->id])->assertNotFound();

        assertDatabaseMissing('plantings', ['farm_id' => $otherFarm->id]);
    });
});

describe('overlapping plantings', function () {
    beforeEach(function () {
        $this->existing = Planting::factory()->for($this->farm)->create([
            'crop_id' => $this->olive->id,
            'started_on' => '2024-01-01',
            'ended_on' => '2024-12-31',
        ]);
        $this->wheat = Crop::query()->where('name', 'Wheat')->sole();
    });

    it('refuses an overlapping planting and names the planting it clashes with', function () {
        post(route('farms.plantings.store', $this->farm), ['crop_id' => $this->wheat->id, 'started_on' => '2024-06-01', 'ended_on' => '2025-06-01'])
            ->assertSessionHasErrors('started_on');

        expect(session('errors')->first('started_on'))->toContain('Olive')->toContain('2024-01-01')->toContain('2024-12-31')
            ->and($this->farm->plantings()->count())->toBe(1);
    });

    it('allows back-to-back plantings', function () {
        post(route('farms.plantings.store', $this->farm), ['crop_id' => $this->wheat->id, 'started_on' => '2024-12-31', 'ended_on' => '2025-06-01'])
            ->assertSessionHasNoErrors();
        post(route('farms.plantings.store', $this->farm), ['crop_id' => $this->wheat->id, 'started_on' => '2023-01-01', 'ended_on' => '2024-01-01'])
            ->assertSessionHasNoErrors();

        expect($this->farm->plantings()->count())->toBe(3);
    });

    it('counts a missing start as the distant past', function () {
        post(route('farms.plantings.store', $this->farm), ['crop_id' => $this->wheat->id, 'ended_on' => '2024-02-01'])
            ->assertSessionHasErrors('started_on');
    });

    it('counts a missing end as the open future', function () {
        post(route('farms.plantings.store', $this->farm), ['crop_id' => $this->wheat->id, 'started_on' => '2024-12-01'])
            ->assertSessionHasErrors('started_on');
        post(route('farms.plantings.store', $this->farm), ['crop_id' => $this->wheat->id, 'started_on' => '2030-01-01'])
            ->assertSessionHasNoErrors();
    });

    it('refuses a second planting with no dates at all', function () {
        post(route('farms.plantings.store', $this->farm), ['crop_id' => $this->wheat->id])->assertSessionHasErrors('started_on');
    });

    it('names an open-ended planting that clashes', function () {
        $this->existing->update(['ended_on' => null]);

        post(route('farms.plantings.store', $this->farm), ['crop_id' => $this->wheat->id, 'ended_on' => '2025-01-01'])
            ->assertSessionHasErrors('started_on');

        expect(session('errors')->first('started_on'))->toContain('Olive (2024-01-01 to now)');
    });

    it('lets plantings on different farms overlap', function () {
        $otherFarm = Farm::factory()->create(['user_id' => $this->user->id]);

        post(route('farms.plantings.store', $otherFarm), ['crop_id' => $this->wheat->id])->assertSessionHasNoErrors();
    });

    it('ignores zone plantings', function () {
        Planting::factory()->for($this->farm)->forZone()->create(['started_on' => '2025-01-01']);

        post(route('farms.plantings.store', $this->farm), ['crop_id' => $this->wheat->id, 'started_on' => '2025-01-01'])->assertSessionHasNoErrors();
    });

    it('does not clash a planting with itself when it is corrected', function () {
        put(route('farms.plantings.update', [$this->farm, $this->existing]), ['crop_id' => $this->olive->id, 'started_on' => '2024-02-01', 'ended_on' => '2024-12-31'])
            ->assertSessionHasNoErrors();
    });

    it('refuses a correction that overlaps another planting', function () {
        $later = Planting::factory()->for($this->farm)->create(['crop_id' => $this->wheat->id, 'started_on' => '2025-01-01']);

        put(route('farms.plantings.update', [$this->farm, $later]), ['crop_id' => $this->wheat->id, 'started_on' => '2024-06-01'])
            ->assertSessionHasErrors('started_on');

        expect($later->fresh()->started_on->toDateString())->toBe('2025-01-01');
    });
});

describe('replacing the current planting', function () {
    beforeEach(function () {
        $this->current = Planting::factory()->for($this->farm)->create(['crop_id' => $this->olive->id, 'started_on' => '2024-01-01']);
        $this->wheat = Crop::query()->where('name', 'Wheat')->sole();
    });

    it('ends the current planting on the new start date and adds the new one', function () {
        post(route('farms.plantings.store', $this->farm), ['crop_id' => $this->wheat->id, 'started_on' => '2026-05-01', 'end_current' => true])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('farms.show', $this->farm));

        expect($this->current->fresh()->ended_on->toDateString())->toBe('2026-05-01');
        assertDatabaseHas('plantings', ['farm_id' => $this->farm->id, 'crop_id' => $this->wheat->id, 'ended_on' => null]);
    });

    it('without the flag, asks to end the current planting and changes nothing', function () {
        post(route('farms.plantings.store', $this->farm), ['crop_id' => $this->wheat->id, 'started_on' => '2026-05-01'])
            ->assertSessionHasErrors(['end_current' => 'Olive (2024-01-01 to now) is still growing. End it on 2026-05-01 to add this crop?']);

        expect($this->current->fresh()->ended_on)->toBeNull()
            ->and($this->farm->plantings()->count())->toBe(1);
    });

    it('replaces a current planting that has no start date', function () {
        $this->current->update(['started_on' => null]);

        post(route('farms.plantings.store', $this->farm), ['crop_id' => $this->wheat->id, 'started_on' => '2026-05-01', 'end_current' => true])
            ->assertSessionHasNoErrors();

        expect($this->current->fresh()->ended_on->toDateString())->toBe('2026-05-01');
    });

    it('does not excuse a clash with an ended planting', function () {
        $this->current->update(['ended_on' => '2026-08-01']);

        post(route('farms.plantings.store', $this->farm), ['crop_id' => $this->wheat->id, 'started_on' => '2026-05-01', 'end_current' => true])
            ->assertSessionHasErrors('started_on');

        expect($this->current->fresh()->ended_on->toDateString())->toBe('2026-08-01')
            ->and($this->farm->plantings()->count())->toBe(1);
    });

    it('does not excuse a clash with a planting that starts later', function () {
        post(route('farms.plantings.store', $this->farm), ['crop_id' => $this->wheat->id, 'started_on' => '2023-01-01', 'end_current' => true])
            ->assertSessionHasErrors('started_on');
        post(route('farms.plantings.store', $this->farm), ['crop_id' => $this->wheat->id, 'started_on' => '2024-01-01', 'end_current' => true])
            ->assertSessionHasErrors('started_on');

        expect($this->current->fresh()->ended_on)->toBeNull()
            ->and($this->farm->plantings()->count())->toBe(1);
    });

    it('does not excuse a new planting with no start date', function () {
        post(route('farms.plantings.store', $this->farm), ['crop_id' => $this->wheat->id, 'end_current' => true])
            ->assertSessionHasErrors('started_on');

        expect($this->current->fresh()->ended_on)->toBeNull();
    });

    it('does not excuse more than one clashing planting', function () {
        $this->current->update(['ended_on' => '2026-01-01']);
        $next = Planting::factory()->for($this->farm)->create(['crop_id' => $this->olive->id, 'started_on' => '2026-01-01']);

        post(route('farms.plantings.store', $this->farm), ['crop_id' => $this->wheat->id, 'started_on' => '2025-06-01', 'end_current' => true])
            ->assertSessionHasErrors('started_on');

        expect($next->fresh()->ended_on)->toBeNull()
            ->and($this->farm->plantings()->count())->toBe(2);
    });

    it('does not replace when a planting is corrected', function () {
        $later = Planting::factory()->for($this->farm)->create(['crop_id' => $this->wheat->id, 'started_on' => '2030-01-01']);

        put(route('farms.plantings.update', [$this->farm, $later]), ['crop_id' => $this->wheat->id, 'started_on' => '2026-05-01', 'end_current' => true])
            ->assertSessionHasErrors('started_on');

        expect($this->current->fresh()->ended_on)->toBeNull()
            ->and($later->fresh()->started_on->toDateString())->toBe('2030-01-01');
    });

    it('does not let another user replace a planting on their farm', function () {
        $otherFarm = Farm::factory()->create();
        $theirs = Planting::factory()->for($otherFarm)->create(['crop_id' => $this->olive->id, 'started_on' => '2024-01-01']);

        post(route('farms.plantings.store', $otherFarm), ['crop_id' => $this->wheat->id, 'started_on' => '2026-05-01', 'end_current' => true])
            ->assertNotFound();

        expect($theirs->fresh()->ended_on)->toBeNull();
        assertDatabaseMissing('plantings', ['farm_id' => $otherFarm->id, 'crop_id' => $this->wheat->id]);
    });
});

describe('correcting a planting', function () {
    beforeEach(function () {
        $this->planting = Planting::factory()->for($this->farm)->create(['crop_id' => $this->olive->id]);
    });

    it('changes the crop, variety and dates', function () {
        $wheat = Crop::query()->where('name', 'Wheat')->sole();

        put(route('farms.plantings.update', [$this->farm, $this->planting]), [
            'crop_id' => $wheat->id,
            'variety' => 'Durum',
            'started_on' => '2025-01-01',
            'ended_on' => '2025-09-01',
        ])->assertRedirect(route('farms.show', $this->farm));

        assertDatabaseHas('plantings', ['id' => $this->planting->id, 'crop_id' => $wheat->id, 'variety' => 'Durum']);
        expect($this->planting->fresh()->ended_on->toDateString())->toBe('2025-09-01');
    });

    it('rejects an end date before the start date', function () {
        put(route('farms.plantings.update', [$this->farm, $this->planting]), ['crop_id' => $this->olive->id, 'started_on' => '2025-01-01', 'ended_on' => '2024-01-01'])
            ->assertSessionHasErrors('ended_on');
    });

    it('rejects a crop another user added', function () {
        $theirs = Crop::factory()->forTenant()->create();

        put(route('farms.plantings.update', [$this->farm, $this->planting]), ['crop_id' => $theirs->id])->assertSessionHasErrors('crop_id');

        assertDatabaseHas('plantings', ['id' => $this->planting->id, 'crop_id' => $this->olive->id]);
    });

    it('does not let another user correct it', function () {
        $otherUser = User::factory()->create();
        $otherFarm = Farm::factory()->create(['user_id' => $otherUser->id]);
        $theirPlanting = Planting::factory()->for($otherFarm)->create(['crop_id' => $this->olive->id]);

        put(route('farms.plantings.update', [$otherFarm, $theirPlanting]), ['crop_id' => $this->olive->id, 'variety' => 'Hacked'])->assertNotFound();
        put(route('farms.plantings.update', [$this->farm, $theirPlanting]), ['crop_id' => $this->olive->id, 'variety' => 'Hacked'])->assertNotFound();

        assertDatabaseMissing('plantings', ['variety' => 'Hacked']);
    });
});

describe('ending a planting', function () {
    it('ends it today by default, so it is past', function () {
        $planting = Planting::factory()->for($this->farm)->create(['crop_id' => $this->olive->id, 'started_on' => '2024-01-01']);

        patch(route('farms.plantings.end', [$this->farm, $planting]))->assertRedirect(route('farms.show', $this->farm));

        expect($planting->fresh()->ended_on->toDateString())->toBe(now()->toDateString());
        get(route('farms.show', $this->farm))->assertInertia(fn (AssertableInertia $page) => $page->where('plantings.0.status', 'past'));
    });

    it('ends it on the given day', function () {
        $planting = Planting::factory()->for($this->farm)->create(['crop_id' => $this->olive->id]);

        patch(route('farms.plantings.end', [$this->farm, $planting]), ['ended_on' => '2026-05-01']);

        expect($planting->fresh()->ended_on->toDateString())->toBe('2026-05-01');
    });

    it('cannot end before it started', function () {
        $planting = Planting::factory()->for($this->farm)->future()->create(['crop_id' => $this->olive->id]);

        patch(route('farms.plantings.end', [$this->farm, $planting]))->assertSessionHasErrors('ended_on');

        expect($planting->fresh()->ended_on)->toBeNull();
    });

    it('does not let another user end it', function () {
        $otherFarm = Farm::factory()->create();
        $theirPlanting = Planting::factory()->for($otherFarm)->create(['crop_id' => $this->olive->id]);

        patch(route('farms.plantings.end', [$otherFarm, $theirPlanting]))->assertNotFound();
        patch(route('farms.plantings.end', [$this->farm, $theirPlanting]))->assertNotFound();

        expect($theirPlanting->fresh()->ended_on)->toBeNull();
    });
});

describe('deleting a planting', function () {
    it('deletes it', function () {
        $planting = Planting::factory()->for($this->farm)->create(['crop_id' => $this->olive->id]);

        delete(route('farms.plantings.destroy', [$this->farm, $planting]))->assertRedirect(route('farms.show', $this->farm));

        assertDatabaseMissing('plantings', ['id' => $planting->id]);
    });

    it('does not let another user delete it', function () {
        $otherFarm = Farm::factory()->create();
        $theirPlanting = Planting::factory()->for($otherFarm)->create(['crop_id' => $this->olive->id]);

        delete(route('farms.plantings.destroy', [$otherFarm, $theirPlanting]))->assertNotFound();
        delete(route('farms.plantings.destroy', [$this->farm, $theirPlanting]))->assertNotFound();

        assertDatabaseHas('plantings', ['id' => $theirPlanting->id]);
    });

    it('is forbidden by the policy for a planting the user does not own', function () {
        $theirPlanting = Planting::factory()->create();

        expect($this->user->can('delete', $theirPlanting))->toBeFalse()
            ->and($this->user->can('update', $theirPlanting))->toBeFalse();
    });
});

describe('zone plantings', function () {
    it('cannot be corrected, ended or deleted through the whole-farm routes', function () {
        $zonePlanting = Planting::factory()->for($this->farm)->forZone()->create(['crop_id' => $this->olive->id]);

        put(route('farms.plantings.update', [$this->farm, $zonePlanting]), ['crop_id' => $this->olive->id, 'variety' => 'Changed'])->assertNotFound();
        patch(route('farms.plantings.end', [$this->farm, $zonePlanting]))->assertNotFound();
        delete(route('farms.plantings.destroy', [$this->farm, $zonePlanting]))->assertNotFound();

        assertDatabaseHas('plantings', ['id' => $zonePlanting->id, 'variety' => null, 'ended_on' => null]);
    });

    it('cannot be reached under another of the user\'s farms', function () {
        $otherFarm = Farm::factory()->create(['user_id' => $this->user->id]);
        $planting = Planting::factory()->for($otherFarm)->create(['crop_id' => $this->olive->id]);

        delete(route('farms.plantings.destroy', [$this->farm, $planting]))->assertNotFound();

        assertDatabaseHas('plantings', ['id' => $planting->id]);
    });
});

describe('deleting a farm', function () {
    it('deletes its plantings, and keeps the crop', function () {
        $planting = Planting::factory()->for($this->farm)->create(['crop_id' => $this->olive->id]);
        $zonePlanting = Planting::factory()->for($this->farm)->forZone()->create(['crop_id' => $this->olive->id]);

        delete(route('farms.destroy', $this->farm))->assertRedirect(route('farms.index'));

        assertDatabaseMissing('plantings', ['id' => $planting->id]);
        assertDatabaseMissing('plantings', ['id' => $zonePlanting->id]);
        assertDatabaseHas('crops', ['id' => $this->olive->id]);
    });
});
