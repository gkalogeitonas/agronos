<?php

use App\Models\User;

test('placeholder pages from the redesign render for authenticated users', function (string $route, string $component) {
    $this->actingAs(User::factory()->create())
        ->get(route($route))
        ->assertInertia(fn ($page) => $page->component($component));
})->with([
    'alerts' => ['alerts.index', 'Alerts/Index'],
    'actuators' => ['actuators.index', 'Actuators/Index'],
]);

test('placeholder pages require authentication', function (string $route) {
    $this->get(route($route))->assertRedirect(route('login'));
})->with(['alerts.index', 'actuators.index']);
