<?php

namespace App\Services;

use App\Models\Trip;
use App\Models\Place;
use Illuminate\Support\Facades\DB;
use App\DTO\TripVote;

class TripPlaceVoteService
{
    public function saveVote(Trip $trip, Place $place, int $userId, int $score): TripVote
    {
        DB::table('trip_place_votes')->updateOrInsert(
            [
                'trip_id'  => $trip->id,
                'place_id' => $place->id,
                'user_id'  => $userId,
            ],
            [
                'score'      => $score,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        return $this->aggregate($trip, $place);
    }

    public function aggregate(Trip $trip, Place $place): TripVote
    {
        $agg = DB::table('trip_place_votes')
            ->selectRaw('AVG(score) as avg_score, COUNT(*) as votes')
            ->where('trip_id', $trip->id)
            ->where('place_id', $place->id)
            ->first();

        return TripVote::fromAggregate($agg);
    }
}
