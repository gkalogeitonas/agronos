<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

it('passes flashed session messages to the page as inertia flash data', function () {
    $user = User::factory()->create();

    $response = actingAs($user)
        ->withSession(['success' => 'Sensor added.', 'error' => 'Something failed.', 'info' => 'Heads up.'])
        ->get(route('sensors.index'));

    $response->assertInertia(fn (Assert $page) => $page
        ->hasFlash('success', 'Sensor added.')
        ->hasFlash('error', 'Something failed.')
        ->hasFlash('info', 'Heads up.')
    );
});

it('sends no flash data when nothing was flashed', function () {
    $user = User::factory()->create();

    $response = actingAs($user)->get(route('sensors.index'));

    $response->assertInertia(fn (Assert $page) => $page
        ->missingFlash('success')
        ->missingFlash('error')
        ->missingFlash('info')
    );
});
