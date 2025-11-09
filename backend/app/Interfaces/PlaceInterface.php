<?php

namespace App\Interfaces;

use App\Models\Trip;
use App\Models\Place;
use App\Models\User;
use Illuminate\Support\Collection;

interface PlaceInterface
{
    public function listForTrip(Trip $trip): Collection;
    public function attachToTrip(Trip $trip, array $data, User $user): array;
    public function updateTripPlace(Trip $trip, Place $place, array $data): array;
    public function detachFromTrip(Trip $trip, Place $place): array;
    public function saveTripVote(Trip $trip, Place $place, User $user, int $score);
}
