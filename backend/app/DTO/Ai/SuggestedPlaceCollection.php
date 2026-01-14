<?php

namespace App\DTO\Ai;

final class SuggestedPlaceCollection
{
    /**
     * @param array<int, SuggestedPlace> $items
     * @param array<string, mixed> $meta
     */
    public function __construct(
        public readonly array $items,
        public readonly array $meta = [],
    ) {}
}
