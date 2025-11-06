<?php

namespace App\Services;

use App\Models\Trip;
use App\Models\Place;
use App\DTO\TripPlace;
use Illuminate\Support\Collection;

class TripPlaceService
{
    public function list(Trip $trip)
    {
        return $trip->places()
            ->withPivot(['status', 'is_fixed', 'day', 'order_index', 'note', 'added_by'])
            ->get();
    }

    public function listAsDto(Trip $trip): Collection
    {
        $places = $this->list($trip);

        return $places->map(fn (Place $place) => TripPlace::fromModel($place));
    }

    public function exists(Trip $trip, int $placeId): bool
    {
        return $trip->places()->where('place_id', $placeId)->exists();
    }

    public function attach(Trip $trip, int $placeId, array $attrs): void
    {
        $trip->places()->attach($placeId, $attrs);
    }

    public function update(Trip $trip, Place $place, array $attrs): void
    {
        $trip->places()->updateExistingPivot($place->id, $attrs);
    }

    public function detach(Trip $trip, Place $place): void
    {
        $trip->places()->detach($place->id);
    }
}
