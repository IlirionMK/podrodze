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

        $users = User::query()->get();

        if ($users->isEmpty()) {
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

            // NOTE:
            // Owner is already attached in Trip::created() hook (role=owner, status=accepted).
            // Do NOT insert owner into trip_user here.

            $members = $users
                ->where('id', '!=', $owner->id)
                ->shuffle()
                ->take(rand(2, min(5, $users->count() - 1)));

            foreach ($members as $member) {
                $trip->members()->syncWithoutDetaching([
                    $member->id => [
                        'role' => fake()->randomElement(['member', 'editor']),
                        'status' => fake()->randomElement(['pending', 'accepted', 'declined']),
                    ],
                ]);
            }
        }

        $this->command->info('Trips and trip_user relations seeded successfully.');
    }
}
