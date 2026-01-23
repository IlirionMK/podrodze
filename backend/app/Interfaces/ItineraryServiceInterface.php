<?php

namespace App\Interfaces;

use App\Models\Trip;
use App\DTO\Itinerary\Itinerary;

interface ItineraryServiceInterface
{
    public function generate(Trip $trip): Itinerary;

    public function generateFullRoute(Trip $trip, int $days, int $radius): Itinerary;

    public function aggregatePreferences(Trip $trip): array;

    public function getSaved(Trip $trip): ?array;

    public function updateSaved(Trip $trip, int $dayCount, array $schedule, string $expectedUpdatedAt): array;
}
