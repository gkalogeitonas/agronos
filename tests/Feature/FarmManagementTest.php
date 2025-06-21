<?php

use App\Models\Farm;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use function Pest\Laravel\{actingAs, get, post, put, delete, assertDatabaseHas, assertDatabaseMissing};

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
        //dd($response->getContent());

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
            'coordinates' => 'POINT(21.7379 38.2466)',
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
