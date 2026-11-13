<?php

use App\Models\Device;
use App\Models\Farm;
use App\Models\User;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->user = User::factory()->create();
    actingAs($this->user);
});

it('shows device commands as disabled and explains why', function () {
    $device = Device::factory()->create(['user_id' => $this->user->id]);

    visit(route('devices.show', $device, absolute: false))
        ->assertSeeIn('span[title^="Coming soon"]', 'Restart device')
        ->assertDisabled('span[title^="Coming soon"] button:has-text("Restart device")')
        ->assertDisabled('span[title^="Coming soon"] button:has-text("Update firmware")')
        ->assertPresent('span[title="Coming soon: device commands are not available yet"]');
});

it('registers a device on the farm from the URL and opens its page', function () {
    $farm = Farm::factory()->create(['user_id' => $this->user->id, 'name' => 'North Field']);

    $page = visit(route('devices.create', ['farm' => $farm->id], absolute: false))
        ->assertSelected('#farm_id', (string) $farm->id)
        ->fill('#name', 'Sensor node 01')
        ->fill('#uuid', 'node-01-uuid')
        ->fill('#secret', 'a-long-secret')
        ->select('#type', 'wifi')
        ->press('Register device');

    $device = Device::query()->where('uuid', 'node-01-uuid')->sole();
    $page->assertPathIs(route('devices.show', $device, absolute: false))
        ->assertSee('Sensor node 01')
        ->assertSee('North Field');

    expect($device)->name->toBe('Sensor node 01')->farm_id->toBe($farm->id);
});

it('opens sensor create with the device filled in from the device page', function () {
    $device = Device::factory()->create(['user_id' => $this->user->id]);

    visit(route('devices.show', $device, absolute: false))
        ->click('Add sensor')
        ->assertPathIs(route('sensors.create', absolute: false))
        ->assertValue('#device_uuid', $device->uuid)
        ->assertNoJavaScriptErrors();
});

it('warns that sensor positions stay when the farm changes on the edit form', function () {
    $farm = Farm::factory()->create(['user_id' => $this->user->id, 'name' => 'North Field']);
    Farm::factory()->create(['user_id' => $this->user->id, 'name' => 'South Field']);
    $device = Device::factory()->onFarm($farm)->create();
    $warning = "This device's sensors keep their positions.";

    visit(route('devices.edit', $device, absolute: false))
        ->assertDontSee($warning)
        ->select('#farm_id', 'South Field')
        ->assertSee($warning)
        ->select('#farm_id', 'North Field')
        ->assertDontSee($warning);
});

it('shows the validation error under the field when the device uuid is already taken', function () {
    Device::factory()->create(['user_id' => $this->user->id, 'uuid' => 'duplicate-uuid']);

    visit(route('devices.create', absolute: false))
        ->fill('#uuid', 'duplicate-uuid')
        ->fill('#secret', 'a-long-secret')
        ->select('#type', 'wifi')
        ->press('Register device')
        ->assertPathIs(route('devices.create', absolute: false))
        ->assertSee('The uuid has already been taken.')
        ->assertDontSee('All Inertia requests must receive a valid Inertia response');
});

it('filters the device list on the server and keeps the filters in the url', function () {
    Device::factory()->create(['user_id' => $this->user->id, 'name' => 'Pump node', 'status' => 'online', 'type' => 'wifi']);
    Device::factory()->create(['user_id' => $this->user->id, 'name' => 'Gate node', 'status' => 'offline', 'type' => 'lora']);

    visit(route('devices.index', absolute: false))
        ->assertSee('Pump node')
        ->assertSee('Gate node')
        ->click('[aria-label="Filter by status"] >> text=Offline')
        ->assertQueryStringHas('status', 'offline')
        ->assertSee('Gate node')
        ->assertDontSee('Pump node')
        ->type('input[type="search"]', 'pump')
        ->assertQueryStringHas('search', 'pump')
        ->assertSee('Showing 0 devices')
        ->assertNoJavaScriptErrors();
});
