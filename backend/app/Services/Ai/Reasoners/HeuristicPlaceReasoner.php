<?php

namespace App\Services\Ai\Reasoners;

use App\Interfaces\Ai\AiPlaceReasonerInterface;

final class HeuristicPlaceReasoner implements AiPlaceReasonerInterface
{
    public function rankAndExplain(array $candidates, array $preferences, array $context, string $locale): array
    {
        $preferred = $this->extractPreferredCategories($preferences);
        $radius = (int) ($context['radius_m'] ?? 5000);

        $results = [];
        foreach ($candidates as $c) {
            $rating = isset($c['rating']) ? (float) $c['rating'] : 0.0;
            $ratingScore = $rating > 0 ? min(1.0, $rating / 5.0) : 0.35;

            $reviews = isset($c['reviews_count']) ? (int) $c['reviews_count'] : null;
            $popularityScore = $reviews !== null
                ? max(0.0, min(1.0, log(max(1, $reviews), 10) / 5.0))
                : 0.4;

            $distanceM = isset($c['distance_m']) ? (int) $c['distance_m'] : null;
            $distanceScore = $distanceM !== null
                ? max(0.0, min(1.0, 1.0 - ($distanceM / max(1, $radius))))
                : 0.5;

            $cat = $c['category'] ?? null;
            $prefBoost = ($cat && in_array($cat, $preferred, true)) ? 0.12 : 0.0;

            $score = (0.45 * $ratingScore) + (0.30 * $popularityScore) + (0.25 * $distanceScore) + $prefBoost;
            $score = max(0.0, min(1.0, $score));

            $estimated = $this->estimateVisitMinutes($cat);
            $reason = $this->buildReason($c, $preferred, $distanceM, $reviews, $locale);

            $results[] = [
                'score' => $score,
                'reason' => $reason,
                'estimated_visit_minutes' => $estimated,
            ];
        }

        return $results;
    }

    private function extractPreferredCategories(array $preferences): array
    {
        // Your project format: ["food"=>1.17, "museum"=>0.33, ...]
        // Also keep compatibility with older shapes.
        if ($this->isWeightsMap($preferences)) {
            $keys = [];
            foreach ($preferences as $key => $weight) {
                if (!is_string($key) || !is_numeric($weight)) {
                    continue;
                }
                if ((float) $weight > 0.0) {
                    $keys[] = strtolower($key);
                }
            }

            // Only recommendable canonical categories
            $recommendable = ['food', 'nightlife', 'museum', 'nature', 'attraction'];

            return array_values(array_unique(array_intersect($keys, $recommendable)));
        }

        // Backward-compatible shapes:
        $raw = $preferences['categories'] ?? ($preferences['preferred_categories'] ?? null);

        if (!is_array($raw)) {
            return [];
        }

        $categories = [];
        foreach ($raw as $item) {
            if (is_string($item)) {
                $categories[] = strtolower($item);
            } elseif (is_array($item) && isset($item['key']) && is_string($item['key'])) {
                $categories[] = strtolower($item['key']);
            }
        }

        return array_values(array_unique($categories));
    }

    private function isWeightsMap(array $arr): bool
    {
        // weights map has string keys and numeric values
        foreach ($arr as $k => $v) {
            if (!is_string($k)) return false;
            if (!is_numeric($v)) return false;
            return true;
        }
        return false;
    }

    private function estimateVisitMinutes(?string $category): int
    {
        return match ($category) {
            'museum' => 120,
            'nature' => 90,
            'attraction' => 60,
            'food' => 75,
            'nightlife' => 120,
            default => 60,
        };
    }

    private function buildReason(array $c, array $preferred, ?int $distanceM, ?int $reviews, string $locale): string
    {
        $name = (string) ($c['name'] ?? 'This place');
        $cat = $c['category'] ?? null;

        $parts = [];

        if ($cat && in_array($cat, $preferred, true)) {
            $parts[] = $this->t($locale, 'Matches your preferences');
        } else {
            $parts[] = $this->t($locale, 'Fits your trip context');
        }

        if ($distanceM !== null) {
            $parts[] = $this->t($locale, 'Close to your current area') . ' (' . $distanceM . 'm)';
        }

        if ($reviews !== null) {
            $parts[] = $this->t($locale, 'Highly popular') . ' (' . $reviews . ')';
        }

        return $name . ': ' . implode('. ', $parts) . '.';
    }

    private function t(string $locale, string $en): string
    {
        return $en;
    }
}
