<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class TripSeeder extends Seeder
{
    public function run(): void
    {
        DB::statement('TRUNCATE TABLE trip_user RESTART IDENTITY CASCADE');
        DB::statement('TRUNCATE TABLE trips RESTART IDENTITY CASCADE');

        $users = User::all();

        if ($users->count() === 0) {
            $this->command->warn('No users found — skipping TripSeeder');
            return;
        }

        foreach (range(1, 30) as $i) {
            $owner = $users->random();

            /** @var \App\Models\Trip $trip */
            $trip = Trip::factory()->create([
                'owner_id' => $owner->id,
                'name' => 'Trip #' . $i . ' — ' . fake()->city(),
            ]);

            DB::table('trip_user')->insert([
                'trip_id'    => $trip->id,
                'user_id'    => $owner->id,
                'role'       => 'owner',
                'status'     => 'accepted',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $members = $users
                ->where('id', '!=', $owner->id)
                ->random(rand(2, min(5, $users->count() - 1)));

            foreach ($members as $member) {
                DB::table('trip_user')->insert([
                    'trip_id'    => $trip->id,
                    'user_id'    => $member->id,
                    'role'       => fake()->randomElement(['member', 'editor']),
                    'status'     => fake()->randomElement(['pending', 'accepted', 'declined']),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        $this->command->info('Trips and trip_user relations seeded successfully.');
    }
}
