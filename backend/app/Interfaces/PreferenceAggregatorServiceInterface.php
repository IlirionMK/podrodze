<?php

namespace App\Interfaces;

use App\Models\Trip;

interface PreferenceAggregatorServiceInterface
{
    /**
     * Calculate average preferences of all trip members (including owner).
     *
     * @param Trip $trip
     * @return array<string, float>
     */
    public function getGroupPreferences(Trip $trip): array;
}
