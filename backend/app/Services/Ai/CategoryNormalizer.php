<?php

namespace App\Services\Ai;

final class CategoryNormalizer
{
    private const RECOMMENDABLE = ['food', 'nightlife', 'museum', 'nature', 'attraction', 'other'];

    public function normalize(?string $raw): string
    {
        $raw = strtolower(trim($raw ?? 'other'));
        $map = (array) config('google_category_map', []);

        $normalized = $map[$raw] ?? ($map['other'] ?? 'other');
        $normalized = strtolower(trim((string) $normalized));

        return $normalized !== '' ? $normalized : 'other';
    }

    public function isRecommendable(string $canonical): bool
    {
        return in_array($canonical, self::RECOMMENDABLE, true);
    }
}
