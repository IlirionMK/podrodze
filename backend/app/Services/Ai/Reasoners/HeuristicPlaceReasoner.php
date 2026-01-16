<?php

namespace App\Services\Ai\Reasoners;

use App\Interfaces\Ai\AiPlaceReasonerInterface;
use Carbon\Carbon;

final class HeuristicPlaceReasoner implements AiPlaceReasonerInterface
{
    public function rankAndExplain(array $candidates, array $preferences, array $context, string $locale): array
    {
        $results = [];
        foreach ($candidates as $c) {
            $rating = (float)($c['rating'] ?? 0);
            $dist = (int)($c['distance_m'] ?? 0);

            $score = ($rating / 5) * 0.7 + (1 - min(1, $dist / 10000)) * 0.3;

            $results[] = [
                'score' => $score,
                'estimated_visit_minutes' => 60,
                'reason' => $locale === 'pl' ? 'Polecane miejsce в Twojej okolicy.' : 'Recommended place in your area.'
            ];
        }
        return $results;
    }
}
