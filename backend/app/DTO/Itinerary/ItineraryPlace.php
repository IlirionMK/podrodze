<?php

namespace App\DTO\Itinerary;

final class ItineraryPlace implements \JsonSerializable
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $category_slug,
        public readonly float $score,
        public readonly float $distance_m,
    ) {}

    public function jsonSerialize(): array
    {
        return [
            'id'            => $this->id,
            'name'          => $this->name,
            'category_slug' => $this->category_slug,
            'score'         => $this->score,
            'distance_m'    => $this->distance_m,
        ];
    }
}
