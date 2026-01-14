<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::factory()->create([
            'name' => 'Owner User',
            'email' => 'owner@example.com',
            'password' => bcrypt('password'),
        ]);

        User::factory()->create([
            'name' => 'Member User',
            'email' => 'member@example.com',
            'password' => bcrypt('password'),
        ]);

        User::factory(100)->create();

        $this->call([
            CategorySeeder::class,
            TripSeeder::class,
            UserPreferenceSeeder::class,
            PlaceSeeder::class,
            TripPlaceSeeder::class,
            TripPlaceVotesSeeder::class,
        ]);
    }
}
