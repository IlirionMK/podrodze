<?php

namespace App\Interfaces;

use App\Models\Trip;
use App\DTO\Itinerary\Itinerary;

interface ItineraryServiceInterface
{
    /**
     * Generate simple one-day recommended itinerary (top places)
     * based on group preferences and nearby places.
     */
    public function generate(Trip $trip): Itinerary;

    /**
     * Generate full multi-day route using synced places, preferences and votes.
     */
    public function generateFullRoute(Trip $trip, int $days, int $radius): Itinerary;

    /**
     * Aggregate group preferences for a given trip.
     *
     * @return array<string, float> e.g. ['museum' => 1.8, 'food' => 2.0]
     */
    public function aggregatePreferences(Trip $trip): array;
}
