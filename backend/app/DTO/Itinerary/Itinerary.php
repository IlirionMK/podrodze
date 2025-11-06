<?php

namespace App\DTO\Itinerary;

class Itinerary
{
    public function __construct(
        public int $trip_id,
        public int $day_count,
        public array $schedule = [],
        public ?array $cache_info = null,
    ) {}
}
