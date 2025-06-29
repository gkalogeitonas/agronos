<?php

namespace Database\Seeders;

use App\Models\Farm;
use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Create the test user
        $user = User::factory()->create([
            'name' => 'johndoe',
            'email' => 'johndoe@gmail.com',
            'password' => bcrypt('password'), // Use bcrypt for password hashing
        ]);

        // Create 10 farms for the test user
        Farm::factory(10)->create([
            'user_id' => $user->id,
        ]);
    }
}
