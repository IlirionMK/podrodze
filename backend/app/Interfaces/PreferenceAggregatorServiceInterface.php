<?php

namespace App\Interfaces;

use App\Models\Trip;

interface PreferenceAggregatorServiceInterface
{
    /**
     * Aggregate average user preferences across all members of a trip.
     *
     * @param Trip $trip
     * @return array<string, float>
     */
    public function getGroupPreferences(Trip $trip): array;
}
