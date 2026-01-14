<?php

namespace App\Interfaces\Ai;

use App\DTO\Ai\PlaceSuggestionQuery;
use App\Models\Trip;

interface PlacesCandidateProviderInterface
{
    /**
     * @return array<int, array{
     *   source: string,
     *   internal_place_id?: int,
     *   external_id?: string,
     *   name: string,
     *   category: string|null,
     *   rating: float|null,
     *   reviews_count?: int|null,
     *   lat: float,
     *   lon: float,
     *   distance_m: int|null
     * }>
     */
    public function getCandidates(Trip $trip, PlaceSuggestionQuery $query, array $preferences, array $context): array;
}
