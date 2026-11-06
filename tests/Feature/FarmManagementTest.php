<?php

use App\Enums\AlertSeverity;
use App\Enums\FarmHealth;
use App\Enums\SensorType;
use App\Models\Alert;
use App\Models\Device;
use App\Models\Farm;
use App\Models\Sensor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\assertDatabaseMissing;
use function Pest\Laravel\delete;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\put;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Create a user that owns farms
    $this->user = User::factory()->create();

    // Create another user to test tenant isolation
    $this->otherUser = User::factory()->create();
});

describe('Index (Viewing Farms List)', function () {
    test('authenticated user can see their farms list', function () {
        // Create farms owned by the authenticated user
        $farms = Farm::factory()->count(3)->create([
            'user_id' => $this->user->id,
        ]);

        actingAs($this->user);

        $response = get(route('farms.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Farms/Index')
            ->has('farms', 3)
            ->where('farmHealthOptions', FarmHealth::options())
        );
    });

    test('user cannot see farms belonging to other users', function () {
        // Create farms owned by another user
        $otherUserFarms = Farm::factory()->count(3)->create([
            'user_id' => $this->otherUser->id,
        ]);

        actingAs($this->user);

        $response = get(route('farms.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Farms/Index')
            ->has('farms', 0)
        );
    });

    test('a farm can be created without a boundary', function () {
        actingAs($this->user);

        post(route('farms.store'), [
            'name' => 'No Boundary Farm',
            'location' => 'Athens, Greece',
            'size' => '',
            'coordinates' => null,
        ])->assertRedirect();

        assertDatabaseHas('farms', ['name' => 'No Boundary Farm', 'user_id' => $this->user->id, 'size' => null]);
    });

    test('farms list shows correct farm information', function () {
        // Create a farm owned by the authenticated user
        $farm = Farm::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Test Farm',
            'location' => 'Athens, Greece',
            'size' => 100.5,
            'coordinates' => [
                'type' => 'Polygon',
                'coordinates' => [[[22.9444, 40.6401], [22.945, 40.6401], [22.945, 40.641], [22.9444, 40.641], [22.9444, 40.6401]]],
            ],
        ]);

        actingAs($this->user);

        $response = get(route('farms.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Farms/Index')
            ->has('farms', 1)
            ->where('farms.0.id', $farm->id)
            ->where('farms.0.name', 'Test Farm')
            ->where('farms.0.location', 'Athens, Greece')
        );
    });
});

describe('Show (Viewing a Single Farm)', function () {
    test('user can view details of their own farm', function () {
        // Create a farm owned by the authenticated user
        $farm = Farm::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Test Farm',
        ]);

        actingAs($this->user);

        $response = get(route('farms.show', $farm));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Farms/Show')
            ->where('farm.id', $farm->id)
            ->where('farm.name', 'Test Farm')
        );
    });

    test('user cannot view a farm they do not own', function () {
        // Create a farm owned by another user
        $otherUserFarm = Farm::factory()->create([
            'user_id' => $this->otherUser->id,
        ]);

        actingAs($this->user);

        $response = get(route('farms.show', $otherUserFarm));

        $response->assertStatus(404);
    });

    test('farm details page shows all required information', function () {
        // Create a farm with detailed information
        $farm = Farm::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Organic Olive Farm',
            'location' => 'Kalamata, Greece',
            'size' => 250.75,
            'coordinates' => 'POINT(22.1142 37.0391)',
            'description' => 'Organic olive farm with over 100 trees',
        ]);

        actingAs($this->user);

        $response = get(route('farms.show', $farm));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Farms/Show')
            ->where('farm.id', $farm->id)
            ->where('farm.name', 'Organic Olive Farm')
            ->where('farm.location', 'Kalamata, Greece')
            ->where('farm.size', 250.75)
            ->where('farm.description', 'Organic olive farm with over 100 trees')
        );
    });

    test('farm details page includes the sensors of the devices on the farm', function () {
        $farm = Farm::factory()->create([
            'user_id' => $this->user->id,
        ]);
        $device = Device::factory()->onFarm($farm)->create();
        $sensors = Sensor::factory()->count(2)->sequence(['name' => 'Probe A'], ['name' => 'Probe B'])->create([
            'device_id' => $device->id,
            'user_id' => $this->user->id,
            'type' => SensorType::MOISTURE->value,
        ]);

        actingAs($this->user);
        $response = get(route('farms.show', $farm));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Farms/Show')
            ->has('sensors', 2)
            ->where('sensors.0.id', $sensors[0]->id)
            ->where('sensors.1.id', $sensors[1]->id)
        );
    });
});

describe('Create (Adding New Farms)', function () {
    test('user can create a new farm', function () {
        actingAs($this->user);

        $farmData = [
            'name' => 'New Test Farm',
            'location' => 'Thessaloniki, Greece',
            'size' => 150.25,
            'coordinates' => [
                'type' => 'Polygon',
                'coordinates' => [[[22.9444, 40.6401], [22.945, 40.6401], [22.945, 40.641], [22.9444, 40.641], [22.9444, 40.6401]]],
            ],
            'description' => 'A test farm in Thessaloniki',
        ];

        $response = post(route('farms.store'), $farmData);

        $response->assertRedirect(route('farms.index'));
        assertDatabaseHas('farms', [
            'name' => 'New Test Farm',
            'location' => 'Thessaloniki, Greece',
            'size' => 150.25,
            'description' => 'A test farm in Thessaloniki',
        ]);
    });

    test('newly created farm is associated with authenticated user', function () {
        actingAs($this->user);

        $farmData = [
            'name' => 'New Test Farm',
            'location' => 'Thessaloniki, Greece',
            'size' => 150.25,
        ];

        $response = post(route('farms.store'), $farmData);

        $farm = Farm::where('name', 'New Test Farm')->first();

        $this->assertEquals($this->user->id, $farm->user_id);
    });

    test('farm validation rules are enforced', function () {
        actingAs($this->user);

        // Submitting with missing required fields
        $response = post(route('farms.store'), [
            // Name is missing
            'location' => 'Thessaloniki, Greece',
            'size' => 150.25,
        ]);

        $response->assertSessionHasErrors(['name']);

        // Submitting with invalid size (negative number)
        $response = post(route('farms.store'), [
            'name' => 'Invalid Farm',
            'location' => 'Thessaloniki, Greece',
            'size' => -10, // Invalid negative size
        ]);

        $response->assertSessionHasErrors(['size']);
    });

    test('farm appears in user farm list after creation', function () {
        actingAs($this->user);

        $farmData = [
            'name' => 'New Test Farm',
            'location' => 'Thessaloniki, Greece',
            'size' => 150.25,
        ];

        $response = post(route('farms.store'), $farmData);

        // After creation, visit the index page and confirm the farm is in the list
        $response = get(route('farms.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Farms/Index')
            ->has('farms')
            ->where('farms.0.name', 'New Test Farm')
        );
    });
});

describe('Update (Modifying Farms)', function () {
    test('user can update details of their own farm', function () {
        $farm = Farm::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Original Farm Name',
            'location' => 'Original Location',
            'size' => 100,
        ]);

        actingAs($this->user);

        $updatedData = [
            'name' => 'Updated Farm Name',
            'location' => 'Updated Location',
            'size' => 200,
        ];

        $response = put(route('farms.update', $farm), $updatedData);

        $response->assertRedirect(route('farms.show', $farm->id));

        $farm->refresh();

        $this->assertEquals('Updated Farm Name', $farm->name);
        $this->assertEquals('Updated Location', $farm->location);
        $this->assertEquals(200, $farm->size);
    });

    test('user cannot update a farm they do not own', function () {
        $otherUserFarm = Farm::factory()->create([
            'user_id' => $this->otherUser->id,
            'name' => 'Other User Farm',
        ]);

        actingAs($this->user);

        $updatedData = [
            'name' => 'Attempted Update',
        ];

        $response = put(route('farms.update', $otherUserFarm), $updatedData);
        // dd($response->getContent());

        $response->assertStatus(404);

        $otherUserFarm->refresh();
        $this->assertEquals('Other User Farm', $otherUserFarm->name);
    });

    test('farm details are correctly updated in the database', function () {
        $farm = Farm::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Original Farm Name',
            'location' => 'Original Location',
            'size' => 100,
            'description' => 'Original description',
        ]);

        actingAs($this->user);

        $updatedData = [
            'name' => 'Fully Updated Farm',
            'location' => 'New Location',
            'size' => 300.50,
            'description' => 'Updated detailed description',
        ];

        $response = put(route('farms.update', $farm), $updatedData);

        assertDatabaseHas('farms', [
            'id' => $farm->id,
            'name' => 'Fully Updated Farm',
            'location' => 'New Location',
            'size' => 300.50,
            'description' => 'Updated detailed description',
        ]);
    });

    test('validation rules prevent invalid updates', function () {
        $farm = Farm::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Original Farm Name',
            'size' => 100,
        ]);

        actingAs($this->user);

        // Try to update with invalid size (negative number)
        $response = put(route('farms.update', $farm), [
            'name' => 'Valid Name',
            'size' => -50, // Invalid negative size
        ]);

        $response->assertSessionHasErrors(['size']);

        $farm->refresh();
        $this->assertEquals(100, $farm->size); // Size should not be updated
    });
});

describe('Delete (Removing Farms)', function () {
    test('deleting a farm keeps its devices, unassigned, and their sensors', function () {
        $farm = Farm::factory()->create(['user_id' => $this->user->id]);
        $device = Device::factory()->onFarm($farm)->create();
        $sensor = Sensor::factory()->create(['user_id' => $this->user->id, 'device_id' => $device->id]);

        actingAs($this->user);

        delete(route('farms.destroy', $farm))->assertRedirect(route('farms.index'));

        expect($device->fresh())->not->toBeNull()
            ->and($device->fresh()->farm_id)->toBeNull();
        assertDatabaseHas('sensors', ['id' => $sensor->id, 'device_id' => $device->id]);
    });

    test('user can delete their own farm', function () {
        $farm = Farm::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Farm to Delete',
        ]);

        actingAs($this->user);

        $response = delete(route('farms.destroy', $farm));

        $response->assertRedirect(route('farms.index'));
        assertDatabaseMissing('farms', ['id' => $farm->id]);
    });

    test('user cannot delete a farm they do not own', function () {
        $otherUserFarm = Farm::factory()->create([
            'user_id' => $this->otherUser->id,
            'name' => 'Other User Farm',
        ]);

        actingAs($this->user);

        $response = delete(route('farms.destroy', $otherUserFarm));

        $response->assertStatus(404);
        assertDatabaseHas('farms', ['id' => $otherUserFarm->id]); // Farm should still exist
    });

    test('associated resources are properly handled after deletion', function () {
        // This test will be updated once we have models related to Farm
        // For now, we'll just test the basic deletion
        $farm = Farm::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Farm with Resources',
        ]);

        // TODO: Add associated resources once those models are created
        // (e.g., sensors, devices, crops)

        actingAs($this->user);

        $response = delete(route('farms.destroy', $farm));

        assertDatabaseMissing('farms', ['id' => $farm->id]);

        // TODO: Verify associated resources are handled according to project requirements
    });
});

describe('Open alerts', function () {
    test('farms list counts the open alerts of the devices on each farm', function () {
        $farm = Farm::factory()->create(['user_id' => $this->user->id, 'name' => 'A farm']);
        $quietFarm = Farm::factory()->create(['user_id' => $this->user->id, 'name' => 'B farm']);
        $device = Device::factory()->offline()->onFarm($farm)->create();
        Alert::factory()->forDevice($device)->create();
        Alert::factory()->forDevice(Device::factory()->offline()->onFarm($farm)->create())->create();
        Alert::factory()->forDevice($device)->resolved()->create();

        actingAs($this->user);

        get(route('farms.index'))->assertInertia(fn ($page) => $page
            ->where('farms.0.id', $farm->id)
            ->where('farms.0.openAlerts', 2)
            ->where('farms.1.id', $quietFarm->id)
            ->where('farms.1.openAlerts', 0)
        );
    });

    test('farm detail lists only the open alerts of that farm', function () {
        $farm = Farm::factory()->create(['user_id' => $this->user->id, 'name' => 'North Orchard']);
        $otherFarm = Farm::factory()->create(['user_id' => $this->user->id]);
        $device = Device::factory()->offline()->onFarm($farm)->create(['name' => 'Pump shed']);
        $alert = Alert::factory()->forDevice($device)->create(['opened_at' => '2026-11-05 12:00:00']);
        Alert::factory()->forDevice($device)->resolved()->create();
        Alert::factory()->forDevice(Device::factory()->offline()->onFarm($otherFarm)->create())->create();
        Alert::factory()->forDevice(Device::factory()->offline()->create(['user_id' => $this->user->id]))->create();

        actingAs($this->user);

        get(route('farms.show', $farm))->assertInertia(fn ($page) => $page
            ->has('alerts', 1)
            ->where('alerts.0', [
                'id' => $alert->id,
                'title' => 'Device offline',
                'severity' => 'danger',
                'device' => 'Pump shed',
                'farm' => 'North Orchard',
                'href' => route('devices.show', $device),
                'opened_at' => '2026-11-05T12:00:00.000000Z',
            ])
        );
    });

    test('farm detail lists five alerts, danger first then newest, with the id as tiebreak', function () {
        $farm = Farm::factory()->create(['user_id' => $this->user->id]);
        $devices = Device::factory()->offline()->onFarm($farm)->count(6)->create();
        $olderDanger = Alert::factory()->forDevice($devices[0])->create(['opened_at' => '2026-11-01 08:00:00']);
        $tiedDangerFirst = Alert::factory()->forDevice($devices[1])->create(['opened_at' => '2026-11-02 08:00:00']);
        $tiedDangerSecond = Alert::factory()->forDevice($devices[2])->create(['opened_at' => '2026-11-02 08:00:00']);
        $olderWarning = Alert::factory()->forDevice($devices[3])->create(['severity' => AlertSeverity::WARNING, 'opened_at' => '2026-11-03 08:00:00']);
        $newerWarning = Alert::factory()->forDevice($devices[4])->create(['severity' => AlertSeverity::WARNING, 'opened_at' => '2026-11-04 08:00:00']);
        Alert::factory()->forDevice($devices[5])->create(['severity' => AlertSeverity::INFO, 'opened_at' => '2026-11-05 08:00:00']);

        actingAs($this->user);

        get(route('farms.show', $farm))->assertInertia(fn ($page) => $page
            ->has('alerts', 5)
            ->where('alerts.0.id', $tiedDangerSecond->id)
            ->where('alerts.1.id', $tiedDangerFirst->id)
            ->where('alerts.2.id', $olderDanger->id)
            ->where('alerts.3.id', $newerWarning->id)
            ->where('alerts.4.id', $olderWarning->id)
        );
    });

    test('moving a device to another farm moves its open alerts and counts with it', function () {
        $from = Farm::factory()->create(['user_id' => $this->user->id, 'name' => 'A farm']);
        $to = Farm::factory()->create(['user_id' => $this->user->id, 'name' => 'B farm']);
        $device = Device::factory()->offline()->onFarm($from)->create();
        $alert = Alert::factory()->forDevice($device)->create();

        $device->update(['farm_id' => $to->id]);

        actingAs($this->user);

        get(route('farms.index'))->assertInertia(fn ($page) => $page
            ->where('farms.0.openAlerts', 0)
            ->where('farms.1.openAlerts', 1)
        );
        get(route('farms.show', $from))->assertInertia(fn ($page) => $page->has('alerts', 0));
        get(route('farms.show', $to))->assertInertia(fn ($page) => $page
            ->has('alerts', 1)
            ->where('alerts.0.id', $alert->id)
        );
    });

    test('an unassigned device has its alerts counted on no farm', function () {
        $farm = Farm::factory()->create(['user_id' => $this->user->id]);
        $device = Device::factory()->offline()->onFarm($farm)->create();
        Alert::factory()->forDevice($device)->create();

        $device->update(['farm_id' => null]);

        actingAs($this->user);

        get(route('farms.index'))->assertInertia(fn ($page) => $page->where('farms.0.openAlerts', 0));
        get(route('farms.show', $farm))->assertInertia(fn ($page) => $page->has('alerts', 0));
    });

    test('farms list does not count the alerts of another user', function () {
        $farm = Farm::factory()->create(['user_id' => $this->user->id]);
        Alert::factory()->forDevice(Device::factory()->offline()->onFarm($farm)->create())->create();
        Alert::factory()->forDevice(Device::factory()->offline()->onFarm($farm)->create())->create(['user_id' => $this->otherUser->id]);

        actingAs($this->user);

        get(route('farms.index'))->assertInertia(fn ($page) => $page->where('farms.0.openAlerts', 1));
    });

    test('farm detail does not list the alerts of another user', function () {
        $farm = Farm::factory()->create(['user_id' => $this->user->id]);
        $ownAlert = Alert::factory()->forDevice(Device::factory()->offline()->onFarm($farm)->create())->create();
        Alert::factory()->forDevice(Device::factory()->offline()->onFarm($farm)->create())->create(['user_id' => $this->otherUser->id]);

        actingAs($this->user);

        get(route('farms.show', $farm))->assertInertia(fn ($page) => $page
            ->has('alerts', 1)
            ->where('alerts.0.id', $ownAlert->id)
        );
    });

    test('deleting a farm keeps the alerts of its devices, on no farm and on the Alerts page', function () {
        $farm = Farm::factory()->create(['user_id' => $this->user->id]);
        $remainingFarm = Farm::factory()->create(['user_id' => $this->user->id]);
        $device = Device::factory()->offline()->onFarm($farm)->create();
        $alert = Alert::factory()->forDevice($device)->create();

        actingAs($this->user);

        delete(route('farms.destroy', $farm))->assertRedirect(route('farms.index'));

        assertDatabaseHas('alerts', ['id' => $alert->id, 'open_marker' => true]);
        get(route('farms.index'))->assertInertia(fn ($page) => $page
            ->where('farms.0.id', $remainingFarm->id)
            ->where('farms.0.openAlerts', 0)
        );
        get(route('alerts.index'))->assertInertia(fn ($page) => $page
            ->has('alerts', 1)
            ->where('alerts.0.id', $alert->id)
            ->where('alerts.0.farm', 'Unassigned')
        );
    });
});
