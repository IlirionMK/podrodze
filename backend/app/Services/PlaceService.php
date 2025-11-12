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
     * Return all places attached to the trip as DTO collection.
     *
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
     * Attach a place to a trip.
     *
     * @throws ModelNotFoundException
     * @throws DomainException
     */
    public function attachToTrip(Trip $trip, array $data, User $user): TripPlace
    {
        $placeId = (int) $data['place_id'];

        /** @var Place|null $place */
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

        /** @var Place $attached */
        $attached = $trip->places()
            ->withPivot(['status', 'is_fixed', 'day', 'order_index', 'note', 'added_by'])
            ->findOrFail($placeId);

        return TripPlace::fromModel($attached);
    }

    /**
     * Update a place entry within a trip.
     *
     * @throws ModelNotFoundException
     */
    public function updateTripPlace(Trip $trip, Place $place, array $data): TripPlace
    {
        /** @var Place|null $attached */
        $attached = $trip->places()->where('places.id', $place->id)->first();

        if (! $attached) {
            throw new ModelNotFoundException('Place not found in this trip.');
        }

        $trip->places()->updateExistingPivot($place->id, $data);

        /** @var Place $updated */
        $updated = $trip->places()
            ->withPivot(['status', 'is_fixed', 'day', 'order_index', 'note', 'added_by'])
            ->findOrFail($place->id);

        return TripPlace::fromModel($updated);
    }

    /**
     * Detach a place from a trip.
     *
     * @throws ModelNotFoundException
     */
    public function detachFromTrip(Trip $trip, Place $place): void
    {
        if (! $trip->places()->where('places.id', $place->id)->exists()) {
            throw new ModelNotFoundException('Place not found in this trip.');
        }

        $trip->places()->detach($place->id);
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

        /** @var object{avg_score: float|null, votes: int} $aggregate */
        $aggregate = DB::table('trip_place_votes')
            ->selectRaw('AVG(score) as avg_score, COUNT(*) as votes')
            ->where('trip_id', $trip->id)
            ->where('place_id', $place->id)
            ->first();

        return TripVote::fromAggregate($aggregate);
    }
}
