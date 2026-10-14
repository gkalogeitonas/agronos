<?php

use App\Models\Device;
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

it('registers a device from the form', function () {
    visit(route('devices.create', absolute: false))
        ->fill('#name', 'Sensor node 01')
        ->fill('#uuid', 'node-01-uuid')
        ->fill('#secret', 'a-long-secret')
        ->select('#type', 'wifi')
        ->press('Register device')
        ->assertPathIs(route('devices.index', absolute: false))
        ->assertSee('Sensor node 01');

    $this->assertDatabaseHas('devices', ['uuid' => 'node-01-uuid', 'name' => 'Sensor node 01', 'user_id' => $this->user->id]);
});

// RegisterDeviceRequest::failedValidation() always answers with JSON, so the Inertia form shows a raw JSON modal
// instead of "The uuid has already been taken." under the field.
it('shows the validation error under the field when the device uuid is already taken')->todo();
