<?php

namespace App\Services\Ai;

final class CategoryNormalizer
{
    private const RECOMMENDABLE = [
        'food', 'nightlife', 'museum', 'nature', 'attraction',
        'park', 'cafe', 'restaurant', 'zoo', 'aquarium', 'gallery', 'other'
    ];

    private const TECHNICAL = ['hotel', 'airport', 'station', 'transport', 'lodging'];

    public function normalize(?string $raw): string
    {
        $raw = $raw ? strtolower(trim($raw)) : 'other';
        $map = (array) config('place_categories', []);
        return $map[$raw] ?? ($map['other'] ?? 'other');
    }

    public function isRecommendable(string $canonical): bool
    {
        return in_array($canonical, self::RECOMMENDABLE, true);
    }

    public function isTechnical(string $canonical): bool
    {
        return in_array($canonical, self::TECHNICAL, true);
    }
}
