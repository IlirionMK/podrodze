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
        $users = User::all();

        if ($users->count() === 0) {
            $this->command->warn('No users found — skipping TripSeeder');
            return;
        }

        foreach (range(1, 30) as $i) {
            $owner = $users->random();

            $trip = Trip::factory()->create([
                'owner_id' => $owner->id,
            ]);

            $members = $users->where('id', '!=', $owner->id)->random(rand(2, 5));

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
    }
}
