<?php

namespace App\DTO\Itinerary;

class ItineraryPlace
{
    public function __construct(
        public int $id,
        public string $name,
        public string $category_slug,
        public float $score,
        public float $distance_m
    ) {}
}
