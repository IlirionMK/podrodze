<?php

namespace App\DTO\Ai;

final class PlaceSuggestionQuery
{
    public function __construct(
        public readonly ?int $basedOnPlaceId,
        public readonly int $limit,
        public readonly int $radiusMeters,
        public readonly string $locale,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            basedOnPlaceId: $data['based_on_place_id'] ?? null,
            limit: (int) ($data['limit'] ?? config('ai.suggestions.default_limit')),
            radiusMeters: (int) ($data['radius_m'] ?? config('ai.suggestions.default_radius_m')),
            locale: (string) ($data['locale'] ?? 'en'),
        );
    }
}
