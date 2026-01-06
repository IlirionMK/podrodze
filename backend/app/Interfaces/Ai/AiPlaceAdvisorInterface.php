<?php

namespace App\Interfaces\Ai;

use App\DTO\Ai\PlaceSuggestionQuery;
use App\DTO\Ai\SuggestedPlaceCollection;
use App\Models\Trip;

interface AiPlaceAdvisorInterface
{
    public function suggestForTrip(Trip $trip, PlaceSuggestionQuery $query): SuggestedPlaceCollection;
}
