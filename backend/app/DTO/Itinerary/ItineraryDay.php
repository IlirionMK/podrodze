<?php

namespace App\DTO\Itinerary;

class ItineraryDay
{
    public function __construct(
        public int $day,
        public array $stops = []
    ) {}
}
