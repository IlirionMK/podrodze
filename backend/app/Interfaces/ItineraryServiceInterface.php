<?php

namespace App\Interfaces;

use App\Models\Trip;
use App\DTO\Itinerary\Itinerary;

interface ItineraryServiceInterface
{
    /**
     * Generate recommended itinerary (cached)
     */
    public function generate(Trip $trip): Itinerary;

    /**
     * Build full itinerary from cached places
     */
    public function buildFull(Trip $trip): Itinerary;

    /**
     * Generate full route (uses sync + prefs + votes)
     */
    public function generateFullRoute(Trip $trip, int $days, int $radius): Itinerary;
}
