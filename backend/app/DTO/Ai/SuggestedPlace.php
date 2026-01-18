<?php

namespace App\DTO\Ai;

final class SuggestedPlace
{
    public function __construct(
        public readonly string $source,
        public readonly ?int $internalPlaceId,
        public readonly ?string $externalId,
        public readonly string $name,
        public readonly ?string $category,
        public readonly ?float $rating,
        public readonly ?int $reviewsCount,
        public readonly float $lat,
        public readonly float $lon,
        public readonly ?int $distanceMeters,
        public readonly ?string $nearPlaceName, // <--- Добавлено новое поле
        public readonly int $estimatedVisitMinutes,
        public readonly float $score,
        public string $reason, // Не readonly, так как мы можем обновлять его через Gemini
        public readonly array $addPayload,
    ) {}
}
