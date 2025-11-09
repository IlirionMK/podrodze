<?php

namespace App\Services;

use App\DTO\Trip\TripPlace;
use App\DTO\Trip\TripVote;
use App\Interfaces\PlaceInterface;
use App\Models\Place;

use App\Models\Trip;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PlaceService implements PlaceInterface
{
    /**
     * Return all places attached to the trip as DTO collection.
     */
    public function listForTrip(Trip $trip): Collection
    {
        return $trip->places()
            ->withPivot(['status', 'is_fixed', 'day', 'order_index', 'note', 'added_by'])
            ->get()
            ->map(fn (Place $p) => TripPlace::fromModel($p));
    }

    /**
     * Attach a place to a trip.
     */
    public function attachToTrip(Trip $trip, array $data, User $user): array
    {
        $placeId = (int) $data['place_id'];

        $exists = $trip->places()->where('places.id', $placeId)->exists();
        if ($exists) {
            return [
                'message' => 'This place is already attached to the trip.',
                'status'  => 409,
            ];
        }

        $trip->places()->attach($placeId, [
            'status'      => $data['status'] ?? 'planned',
            'is_fixed'    => $data['is_fixed'] ?? false,
            'day'         => $data['day'] ?? null,
            'order_index' => $data['order_index'] ?? null,
            'note'        => $data['note'] ?? null,
            'added_by'    => $user->id,
        ]);

        return [
            'message' => 'Place added to trip',
            'status'  => 201,
        ];
    }

    /**
     * Update a place entry within a trip.
     */
    public function updateTripPlace(Trip $trip, Place $place, array $data): array
    {
        if (! $trip->places()->where('places.id', $place->id)->exists()) {
            return [
                'message' => 'Place not found in this trip.',
                'status'  => 404,
            ];
        }

        $trip->places()->updateExistingPivot($place->id, $data);

        return [
            'message' => 'Trip place updated',
            'status'  => 200,
        ];
    }

    /**
     * Detach a place from a trip.
     */
    public function detachFromTrip(Trip $trip, Place $place): array
    {
        if (! $trip->places()->where('places.id', $place->id)->exists()) {
            return [
                'message' => 'Place not found in this trip.',
                'status'  => 404,
            ];
        }

        $trip->places()->detach($place->id);

        return [
            'message' => 'Place removed from trip',
            'status'  => 200,
        ];
    }

    /**
     * Save or update a user's vote for a place in a trip.
     */
    public function saveTripVote(Trip $trip, Place $place, User $user, int $score): TripVote
    {
        DB::table('trip_place_votes')->updateOrInsert(
            [
                'trip_id'  => $trip->id,
                'place_id' => $place->id,
                'user_id'  => $user->id,
            ],
            ['score' => $score]
        );

        $aggregate = DB::table('trip_place_votes')
            ->selectRaw('AVG(score) as avg_score, COUNT(*) as votes')
            ->where('trip_id', $trip->id)
            ->where('place_id', $place->id)
            ->first();;

        return TripVote::fromAggregate($aggregate);
    }

    /**
     * Find nearby places using PostGIS.
     */
    public function findNearby(float $lat, float $lon, int $radius = 2000, int $limit = 50): Collection
    {
        $rows = DB::select("
            SELECT id, name, category_slug, rating,
                   ST_Distance(
                       location::geography,
                       ST_SetSRID(ST_MakePoint(:lon, :lat), 4326)::geography
                   ) AS distance_m
            FROM places
            WHERE ST_DWithin(
                location::geography,
                ST_SetSRID(ST_MakePoint(:lon2, :lat2), 4326)::geography,
                :radius
            )
            ORDER BY distance_m ASC
            LIMIT :limit
        ", [
            'lat' => $lat,
            'lon' => $lon,
            'lat2' => $lat,
            'lon2' => $lon,
            'radius' => $radius,
            'limit' => $limit,
        ]);

        return collect($rows);
    }
}
