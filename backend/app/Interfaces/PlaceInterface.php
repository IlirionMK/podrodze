<?php

namespace App\Interfaces;

use App\DTO\Trip\TripPlace;
use App\DTO\Trip\TripVote;
use App\Models\Place;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Support\Collection;

interface PlaceInterface
{
    public function listForTrip(Trip $trip): Collection;

    public function createCustomPlace(array $data, User $user): Place;

    public function createFromGoogle(array $data, User $user): Place;

    public function addToTrip(Trip $trip, array $data, User $user): TripPlace;

    public function attachToTrip(Trip $trip, array $data, User $user): TripPlace;

    public function updateTripPlace(Trip $trip, Place $place, array $data): TripPlace;

    public function detachFromTrip(Trip $trip, Place $place, User $user): void;

    public function saveTripVote(Trip $trip, Place $place, User $user, int $score): TripVote;

    public function findNearby(float $lat, float $lon, int $radius = 2000): Collection;
}
