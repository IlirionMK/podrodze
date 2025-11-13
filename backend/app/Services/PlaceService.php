<?php

namespace App\Services;

use App\DTO\Trip\TripPlace;
use App\DTO\Trip\TripVote;
use App\Interfaces\PlaceInterface;
use App\Models\Place;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use DomainException;

class PlaceService implements PlaceInterface
{
    /**
     * @return Collection<int, TripPlace>
     */
    public function listForTrip(Trip $trip): Collection
    {
        return $trip->places()
            ->withPivot(['status', 'is_fixed', 'day', 'order_index', 'note', 'added_by'])
            ->get()
            ->map(fn(Place $p) => TripPlace::fromModel($p));
    }

    /**
     * @throws ModelNotFoundException
     * @throws DomainException
     */
    public function attachToTrip(Trip $trip, array $data, User $user): TripPlace
    {
        $placeId = (int) $data['place_id'];

        $place = Place::find($placeId);

        if (! $place) {
            throw new ModelNotFoundException("Place #{$placeId} not found.");
        }

        if ($trip->places()->where('places.id', $placeId)->exists()) {
            throw new DomainException('This place is already attached to the trip.');
        }

        $trip->places()->attach($placeId, [
            'status'      => $data['status'] ?? 'planned',
            'is_fixed'    => $data['is_fixed'] ?? false,
            'day'         => $data['day'] ?? null,
            'order_index' => $data['order_index'] ?? null,
            'note'        => $data['note'] ?? null,
            'added_by'    => $user->id,
        ]);

        $attached = $trip->places()
            ->withPivot(['status', 'is_fixed', 'day', 'order_index', 'note', 'added_by'])
            ->findOrFail($placeId);

        return TripPlace::fromModel($attached);
    }

    /**
     * @throws ModelNotFoundException
     */
    public function updateTripPlace(Trip $trip, Place $place, array $data): TripPlace
    {
        $attached = $trip->places()->where('places.id', $place->id)->first();

        if (! $attached) {
            throw new ModelNotFoundException('Place not found in this trip.');
        }

        $trip->places()->updateExistingPivot($place->id, $data);

        $updated = $trip->places()
            ->withPivot(['status', 'is_fixed', 'day', 'order_index', 'note', 'added_by'])
            ->findOrFail($place->id);

        return TripPlace::fromModel($updated);
    }

    /**
     * @throws ModelNotFoundException
     */
    public function detachFromTrip(Trip $trip, Place $place): void
    {
        if (! $trip->places()->where('places.id', $place->id)->exists()) {
            throw new ModelNotFoundException('Place not found in this trip.');
        }

        $trip->places()->detach($place->id);
    }

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
            ->first();

        return TripVote::fromAggregate($aggregate);
    }

    /**
     * Find nearby places using PostGIS (clean service layer).
     *
     * @return Collection<int, Place>
     */
    public function findNearby(float $lat, float $lon, int $radius = 2000): Collection
    {
        return Place::query()
            ->select('places.*')
            ->addSelect([
                DB::raw('ST_Y(location::geometry) AS lat'),
                DB::raw('ST_X(location::geometry) AS lon'),
                DB::raw("
                    ST_Distance(
                        location::geography,
                        ST_SetSRID(ST_MakePoint({$lon}, {$lat}), 4326)::geography
                    ) AS distance_m
                "),
            ])
            ->whereRaw("
                ST_DWithin(
                    location::geography,
                    ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography,
                    ?
                )
            ", [$lon, $lat, $radius])
            ->orderBy('distance_m')
            ->get();
    }
}
