<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TripPlaceVotesSeeder extends Seeder
{
    public function run(): void
    {
        DB::statement('TRUNCATE TABLE trip_place_votes RESTART IDENTITY CASCADE');

        $trips = DB::table('trips')->select('id', 'owner_id')->get();

        if ($trips->isEmpty()) {
            $this->command->warn('No trips found — skipping TripPlaceVotesSeeder');
            return;
        }

        foreach ($trips as $trip) {
            $participants = $this->getTripParticipants((int) $trip->id, (int) $trip->owner_id);

            $tripPlaces = DB::table('trip_place')
                ->where('trip_id', $trip->id)
                ->pluck('place_id');

            if ($tripPlaces->isEmpty()) {
                continue;
            }

            foreach ($tripPlaces as $placeId) {
                if (rand(0, 100) < 25) {
                    continue;
                }

                $maxVoters = min(6, $participants->count());
                $voterCount = rand(1, max(1, $maxVoters));

                $voters = $participants->shuffle()->take($voterCount);

                foreach ($voters as $userId) {
                    DB::table('trip_place_votes')->insert([
                        'trip_id' => $trip->id,
                        'place_id' => $placeId,
                        'user_id' => $userId,
                        'score' => rand(1, 5),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }

        $this->command->info('Trip place votes seeded successfully.');
    }

    private function getTripParticipants(int $tripId, int $ownerId): Collection
    {
        $accepted = DB::table('trip_user')
            ->where('trip_id', $tripId)
            ->where('status', 'accepted')
            ->pluck('user_id');

        $participants = $accepted->push($ownerId)->unique()->values();

        if ($participants->isEmpty()) {
            return collect([$ownerId]);
        }

        return $participants;
    }
}
