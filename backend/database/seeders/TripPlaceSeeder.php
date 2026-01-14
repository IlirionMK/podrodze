<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TripPlaceSeeder extends Seeder
{
    public function run(): void
    {
        DB::statement('TRUNCATE TABLE trip_place RESTART IDENTITY CASCADE');

        $trips = DB::table('trips')->select('id', 'owner_id')->get();
        $placeIds = DB::table('places')->pluck('id');

        if ($trips->isEmpty()) {
            $this->command->warn('No trips found — skipping TripPlaceSeeder');
            return;
        }

        if ($placeIds->isEmpty()) {
            $this->command->warn('No places found — skipping TripPlaceSeeder');
            return;
        }

        foreach ($trips as $trip) {
            $participants = $this->getTripParticipants($trip->id, (int) $trip->owner_id);

            $dayCount = rand(1, 5);
            $placesToAttach = rand(6, min(12, $placeIds->count()));

            $picked = $placeIds->shuffle()->take($placesToAttach)->values();

            $orderByDay = array_fill(1, $dayCount, 0);

            foreach ($picked as $placeId) {
                $day = rand(1, $dayCount);
                $orderIndex = $orderByDay[$day]++;

                DB::table('trip_place')->insert([
                    'trip_id' => $trip->id,
                    'place_id' => $placeId,
                    'day' => $day,
                    'order_index' => $orderIndex,
                    'status' => collect(['planned', 'visited', 'skipped'])->random(),
                    'note' => (rand(0, 4) === 0) ? 'Seed note' : null,
                    'is_fixed' => (bool) (rand(0, 7) === 0),
                    'added_by' => $participants->random(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        $this->command->info('Trip places seeded successfully.');
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
