<?php

use App\Models\Device;
use App\Models\Farm;
use App\Models\Sensor;
use App\Models\User;

use function Pest\Laravel\actingAs;

it('loads the redesigned pages without JavaScript errors', function () {
    $user = User::factory()->create();
    $farm = Farm::factory()->create(['user_id' => $user->id]);
    $device = Device::factory()->create(['user_id' => $user->id]);
    $sensor = Sensor::factory()->create(['user_id' => $user->id, 'farm_id' => $farm->id, 'device_id' => $device->id, 'type' => 'moisture']);

    actingAs($user);

    visit([
        route('dashboard', absolute: false),
        route('farms.index', absolute: false),
        route('farms.show', $farm, absolute: false),
        route('farms.create', absolute: false),
        route('farms.edit', $farm, absolute: false),
        route('devices.index', absolute: false),
        route('devices.show', $device, absolute: false),
        route('devices.create', absolute: false),
        route('sensors.index', absolute: false),
        route('sensors.show', $sensor, absolute: false),
        route('sensors.edit', $sensor, absolute: false),
        route('alerts.index', absolute: false),
        route('actuators.index', absolute: false),
    ])->assertNoJavaScriptErrors();
});

it('loads the home page for guests and signed-in users without JavaScript errors', function () {
    visit(route('home', absolute: false))
        ->assertSee('Know your fields before you walk them.')
        ->assertSee('Create account')
        ->assertNoJavaScriptErrors();

    actingAs(User::factory()->create());

    visit(route('home', absolute: false))
        ->assertSee('Open dashboard')
        ->assertDontSee('Create account')
        ->assertNoJavaScriptErrors();
});
