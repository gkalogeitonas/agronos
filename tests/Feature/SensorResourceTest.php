<?php

use App\Models\Sensor;
use App\Models\User;
use App\Models\Device;
use Illuminate\Foundation\Testing\RefreshDatabase;
use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\put;
use function Pest\Laravel\delete;

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
    $response = get('/sensors/' . $sensor->id);
    $response->assertOk();
    $response->assertSee('Show Sensor');
});

it('can create a sensor', function () {
    $payload = [
        'uuid' => 'test-create-uuid',
        'device_id' => $this->device->id,
        'user_id' => $this->user->id,
        'lat' => 1.23,
        'lon' => 4.56,
    ];
    $response = post('/sensors', $payload);
    $response->assertRedirect();
    $this->assertDatabaseHas('sensors', ['uuid' => 'test-create-uuid']);
});

it('can update a sensor', function () {
    $sensor = Sensor::factory()->create(['user_id' => $this->user->id, 'device_id' => $this->device->id]);
    $payload = [
        'lat' => 99.99,
        'lon' => 88.88,
    ];
    $response = put('/sensors/' . $sensor->id, $payload);
    $response->assertRedirect();
    $this->assertDatabaseHas('sensors', ['id' => $sensor->id, 'lat' => 99.99, 'lon' => 88.88]);
});

it('can delete a sensor', function () {
    $sensor = Sensor::factory()->create(['user_id' => $this->user->id, 'device_id' => $this->device->id]);
    $response = delete('/sensors/' . $sensor->id);
    $response->assertRedirect();
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
    $response = get('/sensors/' . $sensor->id);
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
    $response = put('/sensors/' . $sensor->id, $payload);
    $response->assertNotFound();
    $this->assertDatabaseMissing('sensors', ['id' => $sensor->id, 'lat' => 55.55, 'lon' => 44.44]);
});
