<?php

namespace App\Interfaces\Ai;

interface AiPlaceReasonerInterface
{
    /**
     * @param array<int, array{
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
     * }> $candidates
     *
     * @return array<int, array{
     *   score: float,
     *   reason: string,
     *   estimated_visit_minutes: int|null
     * }>
     */
    public function rankAndExplain(array $candidates, array $preferences, array $context, string $locale): array;
}
