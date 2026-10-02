<?php

use App\Models\User;
use Database\Seeders\DatabaseSeeder;

test('the database seeder creates a verified demo user', function () {
    $this->seed(DatabaseSeeder::class);

    $user = User::query()->where('email', 'johndoe@gmail.com')->sole();

    expect($user->name)->toBe('john Doe')
        ->and($user->email_verified_at)->not->toBeNull();
});

test('the database seeder can run more than once', function () {
    $this->seed(DatabaseSeeder::class);
    $this->seed(DatabaseSeeder::class);

    expect(User::query()->where('email', 'johndoe@gmail.com')->count())->toBe(1);
});
