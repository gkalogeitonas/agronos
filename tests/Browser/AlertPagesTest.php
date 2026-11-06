<?php

use App\Enums\AlertSeverity;
use App\Models\Alert;
use App\Models\Device;
use App\Models\Farm;
use App\Models\User;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->user = User::factory()->create();
    actingAs($this->user);
});

it('filters open alerts by severity and links a line to its device', function () {
    $farm = Farm::factory()->create(['user_id' => $this->user->id, 'name' => 'North Orchard']);
    $danger = Device::factory()->offline()->onFarm($farm)->create(['name' => 'Pump shed']);
    $warning = Device::factory()->offline()->create(['user_id' => $this->user->id, 'name' => 'Gate node']);
    Alert::factory()->forDevice($danger)->create(['opened_at' => now()->subMinutes(12)]);
    Alert::factory()->forDevice($warning)->create(['severity' => AlertSeverity::WARNING]);

    visit(route('alerts.index', absolute: false))
        ->assertDontSee('SAMPLE DATA')
        ->assertDontSee('Gateway not reporting')
        ->assertSee('Alerts open automatically when a device stops reporting or its battery runs low, and close on their own once the problem clears.')
        ->assertSee('Pump shed · North Orchard · 12 min ago')
        ->assertSee('Gate node · Unassigned · just now')
        ->click('button:has-text("Warning")')
        ->assertSee('Gate node')
        ->assertDontSee('Pump shed')
        ->click('button:has-text("Critical")')
        ->assertSee('Pump shed')
        ->assertDontSee('Gate node')
        ->click('a:has-text("Device offline")')
        ->assertPathIs(route('devices.show', $danger, absolute: false))
        ->assertNoJavaScriptErrors();
});

it('shows the real open alerts on the overview', function () {
    $device = Device::factory()->offline()->create(['user_id' => $this->user->id, 'name' => 'Pump shed']);
    Alert::factory()->forDevice($device)->create(['opened_at' => now()->subMinutes(12)]);

    $page = visit(route('dashboard', absolute: false))
        ->assertSee('Pump shed · Unassigned · 12 min ago')
        ->assertDontSee('Gateway not reporting')
        ->assertSee('SAMPLE DATA')
        ->assertNoJavaScriptErrors();

    expect($page->text('section[aria-label="Fleet health"]'))->not->toContain('Sample data');
});

it('shows the real open alerts of a farm on the farms list and the farm detail', function () {
    $farm = Farm::factory()->create(['user_id' => $this->user->id, 'name' => 'North Orchard']);
    $device = Device::factory()->offline()->onFarm($farm)->create(['name' => 'Pump shed']);
    Alert::factory()->forDevice($device)->create(['opened_at' => now()->subMinutes(12)]);

    $list = visit(route('farms.index', absolute: false))
        ->assertSee('SAMPLE DATA')
        ->assertNoJavaScriptErrors();

    expect($list->text('[role="row"]:nth-child(2) [role="cell"]:nth-child(5)'))->toBe('1');

    visit(route('farms.show', $farm, absolute: false))
        ->assertSee('Pump shed · North Orchard · 12 min ago')
        ->assertDontSee('Zone B below threshold')
        ->assertSee('SAMPLE DATA')
        ->assertNoJavaScriptErrors();
});
