<?php

use App\Models\Alert;
use App\Models\Device;
use App\Models\Farm;
use App\Models\User;

it('redirects guests to the login page', function () {
    $this->get(route('alerts.index'))->assertRedirect(route('login'));
});

it('renders an alert line with its title, device, farm, link and opening time', function () {
    $user = User::factory()->create();
    $farm = Farm::factory()->create(['user_id' => $user->id, 'name' => 'North Orchard']);
    $device = Device::factory()->offline()->onFarm($farm)->create(['name' => 'Pump shed']);
    $alert = Alert::factory()->forDevice($device)->create(['opened_at' => '2026-11-05 12:00:00']);

    $response = $this->actingAs($user)->get(route('alerts.index'));

    $response->assertInertia(fn ($page) => $page->component('Alerts/Index')
        ->has('alerts', 1)
        ->where('alerts.0', [
            'id' => $alert->id,
            'title' => 'Device offline',
            'severity' => 'danger',
            'device' => 'Pump shed',
            'farm' => 'North Orchard',
            'href' => route('devices.show', $device),
            'opened_at' => '2026-11-05T12:00:00.000000Z',
        ]));
});

it('renders a low battery alert on an unassigned device next to its offline alert', function () {
    $user = User::factory()->create();
    $device = Device::factory()->offline()->lowBattery()->create(['user_id' => $user->id, 'name' => 'Pump shed', 'farm_id' => null]);
    Alert::factory()->forDevice($device)->create(['opened_at' => '2026-11-05 12:00:00']);
    $alert = Alert::factory()->forDevice($device)->lowBattery()->create(['opened_at' => '2026-11-05 12:30:00']);

    $response = $this->actingAs($user)->get(route('alerts.index'));

    $response->assertInertia(fn ($page) => $page
        ->has('alerts', 2)
        ->where('alerts.0', [
            'id' => $alert->id,
            'title' => 'Low battery',
            'severity' => 'warning',
            'device' => 'Pump shed',
            'farm' => 'Unassigned',
            'href' => route('devices.show', $device),
            'opened_at' => '2026-11-05T12:30:00.000000Z',
        ])
        ->where('alerts.1.title', 'Device offline'));
});

it('falls back to the uuid and to Unassigned for a nameless device without a farm', function () {
    $user = User::factory()->create();
    $device = Device::factory()->offline()->create(['user_id' => $user->id, 'name' => null, 'uuid' => 'c0ffee00-0000-4000-8000-000000000001']);
    Alert::factory()->forDevice($device)->create();

    $response = $this->actingAs($user)->get(route('alerts.index'));

    $response->assertInertia(fn ($page) => $page
        ->where('alerts.0.device', 'c0ffee00-0000-4000-8000-000000000001')
        ->where('alerts.0.farm', 'Unassigned'));
});

it('shows the current names of a renamed device and farm', function () {
    $user = User::factory()->create();
    $farm = Farm::factory()->create(['user_id' => $user->id, 'name' => 'Old farm']);
    $device = Device::factory()->offline()->onFarm($farm)->create(['name' => 'Old device']);
    Alert::factory()->forDevice($device)->create();

    $device->update(['name' => 'New device']);
    $farm->update(['name' => 'New farm']);
    $response = $this->actingAs($user)->get(route('alerts.index'));

    $response->assertInertia(fn ($page) => $page
        ->where('alerts.0.device', 'New device')
        ->where('alerts.0.farm', 'New farm'));
});

it('lists open alerts newest first with the id as tiebreak and skips resolved ones', function () {
    $user = User::factory()->create();
    $devices = Device::factory()->offline()->count(4)->create(['user_id' => $user->id]);
    $oldest = Alert::factory()->forDevice($devices[0])->create(['opened_at' => '2026-11-01 08:00:00']);
    $tiedFirst = Alert::factory()->forDevice($devices[1])->create(['opened_at' => '2026-11-03 08:00:00']);
    $tiedSecond = Alert::factory()->forDevice($devices[2])->create(['opened_at' => '2026-11-03 08:00:00']);
    Alert::factory()->forDevice($devices[3])->resolved()->create(['opened_at' => '2026-11-04 08:00:00']);

    $response = $this->actingAs($user)->get(route('alerts.index'));

    $response->assertInertia(fn ($page) => $page
        ->has('alerts', 3)
        ->where('alerts.0.id', $tiedSecond->id)
        ->where('alerts.1.id', $tiedFirst->id)
        ->where('alerts.2.id', $oldest->id));
});

it('does not show the alerts of another user', function () {
    Alert::factory()->create();

    $response = $this->actingAs(User::factory()->create())->get(route('alerts.index'));

    $response->assertInertia(fn ($page) => $page->has('alerts', 0));
});

it('ignores an alert whose subject is gone', function () {
    $user = User::factory()->create();
    $device = Device::factory()->offline()->create(['user_id' => $user->id]);
    Alert::factory()->forDevice($device)->create();
    Device::allTenants()->whereKey($device->id)->toBase()->delete();

    $response = $this->actingAs($user)->get(route('alerts.index'));

    $response->assertOk()->assertInertia(fn ($page) => $page->has('alerts', 0));
});

it('removes all alerts of a deleted device and leaves other devices alerts alone', function () {
    $user = User::factory()->create();
    $device = Device::factory()->offline()->create(['user_id' => $user->id]);
    $open = Alert::factory()->forDevice($device)->create();
    $resolved = Alert::factory()->forDevice($device)->resolved()->create();
    $other = Alert::factory()->forDevice(Device::factory()->offline()->create(['user_id' => $user->id]))->create();

    $response = $this->actingAs($user)->delete(route('devices.destroy', $device));

    $response->assertRedirect(route('devices.index'));
    $this->assertModelMissing($open);
    $this->assertModelMissing($resolved);
    $this->assertModelExists($other);
});

it('keeps the alerts of a device another user tries to delete', function () {
    $alert = Alert::factory()->create();

    $response = $this->actingAs(User::factory()->create())->delete(route('devices.destroy', $alert->subject_id));

    $response->assertNotFound();
    $this->assertModelExists($alert);
});

it('removes the alerts of a deleted account', function () {
    $user = User::factory()->create();
    $alert = Alert::factory()->forDevice(Device::factory()->offline()->create(['user_id' => $user->id]))->create();

    $response = $this->actingAs($user)->delete(route('profile.destroy'), ['password' => 'password']);

    $response->assertRedirect('/');
    $this->assertModelMissing($user);
    $this->assertModelMissing($alert);
});
