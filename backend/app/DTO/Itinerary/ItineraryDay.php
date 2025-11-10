<?php

namespace App\DTO\Itinerary;

final class ItineraryDay implements \JsonSerializable
{
    /**
     * @param ItineraryPlace[] $places
     */
    public function __construct(
        public readonly int $day,
        public readonly array $places = [],
    ) {}

    public function jsonSerialize(): array
    {
        return [
            'day'    => $this->day,
            'places' => $this->places,
        ];
    }
}
