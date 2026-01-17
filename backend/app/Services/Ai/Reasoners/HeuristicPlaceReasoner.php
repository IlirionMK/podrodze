<?php

namespace App\Services\Ai\Reasoners;

use App\Interfaces\Ai\AiPlaceReasonerInterface;

final class HeuristicPlaceReasoner implements AiPlaceReasonerInterface
{
    public function rankAndExplain(array $candidates, array $preferences, array $context, string $locale): array
    {
        $results = [];
        foreach ($candidates as $c) {
            $rating = (float)($c['rating'] ?? 0);
            $reviews = (int)($c['reviews_count'] ?? 0);
            $prefWeight = (float)($preferences[$c['category'] ?? 'other'] ?? 0);

            // Формула: Рейтинг 40%, Популярность 30%, Интересы 30%
            $popularity = min(1, $reviews / 1000);
            $score = ($rating / 5) * 0.4 + ($popularity * 0.3) + ($prefWeight * 0.3);

            $results[] = [
                'score' => $score,
                'reason' => $this->buildSmartReason($c, $rating, $reviews, $locale)
            ];
        }
        return $results;
    }

    private function buildSmartReason(array $c, float $rating, int $reviews, string $locale): string
    {
        $near = $c['near_place_name'] ?? null;

        if ($reviews > 500 && $rating >= 4.5) {
            $text = $locale === 'pl' ? "Bardzo popularne miejsce" : "Highly popular spot";
            if ($near) $text .= ($locale === 'pl' ? " blisko {$near}" : " near {$near}");
            return "{$text} ({$reviews} opinii).";
        }

        $text = $locale === 'pl' ? "Polecane na podstawie Twoich zainteresowań" : "Recommended based on your interests";
        if ($near) $text .= ($locale === 'pl' ? " blisko {$near}" : " near {$near}");
        return $text . ".";
    }
}
