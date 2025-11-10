<?php

namespace App\DTO\Itinerary;

final class Itinerary implements \JsonSerializable
{
    /**
     * @param ItineraryDay[] $schedule
     */
    public function __construct(
        public readonly int $trip_id,
        public readonly int $day_count,
        public readonly array $schedule = [],
        public readonly ?array $cache_info = null,
    ) {}

    public function jsonSerialize(): array
    {
        return [
            'trip_id'    => $this->trip_id,
            'day_count'  => $this->day_count,
            'schedule'   => $this->schedule,
            'cache_info' => $this->cache_info,
        ];
    }
}
