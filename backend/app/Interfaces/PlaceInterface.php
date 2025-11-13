<?php

namespace App\Interfaces;

use App\DTO\Trip\TripPlace;
use App\DTO\Trip\TripVote;
use App\Models\Trip;
use App\Models\Place;
use App\Models\User;
use Illuminate\Support\Collection;

interface PlaceInterface
{
    /** @return Collection<int, TripPlace> */
    public function listForTrip(Trip $trip): Collection;

    public function attachToTrip(Trip $trip, array $data, User $user): TripPlace;

    public function updateTripPlace(Trip $trip, Place $place, array $data): TripPlace;

    public function detachFromTrip(Trip $trip, Place $place): void;

    public function saveTripVote(Trip $trip, Place $place, User $user, int $score): TripVote;

    /**
     * Find nearby places using PostGIS (lat/lon/distance).
     *
     * @return Collection<int, Place>
     */
    public function findNearby(float $lat, float $lon, int $radius = 2000): Collection;
}
