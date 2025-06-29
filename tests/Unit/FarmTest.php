<?php

use App\Models\Farm;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Farm model', function () {
    it('can be created with valid attributes', function () {
        $user = User::factory()->create();
        $farm = Farm::factory()->create(['user_id' => $user->id]);
        expect($farm)->toBeInstanceOf(Farm::class);
        expect($farm->user_id)->toBe($user->id);
    });

    it('belongs to a user', function () {
        $user = User::factory()->create();
        $farm = Farm::factory()->create(['user_id' => $user->id]);
        expect($farm->user)->toBeInstanceOf(User::class);
    });

    it('calculates center attribute', function () {
        $user = User::factory()->create();
        $farm = Farm::factory()->create([
            'user_id' => $user->id,
            'coordinates' => [
                'coordinates' => [
                    [[0,0],[2,0],[2,2],[0,2],[0,0]]
                ]
            ]
        ]);
        $center = $farm->center;
        expect($center)->toHaveKeys(['lng', 'lat']);
    });
});
