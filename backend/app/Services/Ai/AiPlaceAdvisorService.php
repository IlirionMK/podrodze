<?php

namespace App\Services\Ai;

use App\DTO\Ai\PlaceSuggestionQuery;
use App\DTO\Ai\SuggestedPlace;
use App\DTO\Ai\SuggestedPlaceCollection;
use App\Interfaces\Ai\AiPlaceAdvisorInterface;
use App\Interfaces\Ai\AiPlaceReasonerInterface;
use App\Interfaces\Ai\PlacesCandidateProviderInterface;
use App\Interfaces\PreferenceAggregatorServiceInterface;
use App\Models\Place;
use App\Models\Trip;
use App\Services\Activity\ActivityLogger;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

final class AiPlaceAdvisorService implements AiPlaceAdvisorInterface
{
    public function __construct(
        private readonly PreferenceAggregatorServiceInterface $preferences,
        private readonly PlacesCandidateProviderInterface $candidateProvider,
        private readonly AiPlaceReasonerInterface $reasoner,
        private readonly ActivityLogger $activityLogger,
        private readonly CategoryNormalizer $categories,
    ) {}

    public function suggestForTrip(Trip $trip, PlaceSuggestionQuery $query): SuggestedPlaceCollection
    {
        if (!config('ai.suggestions.enabled')) {
            return new SuggestedPlaceCollection(items: [], meta: [
                'trip_id' => $trip->id,
                'disabled' => true,
            ]);
        }

        $query = $this->clampQuery($query);

        $prefs = $this->preferences->getGroupPreferences($trip);
        $prefsHash = hash('sha256', json_encode($prefs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        $context = $this->buildContext($trip, $query);

        $cacheKey = $this->cacheKey($trip->id, $query, $prefsHash, $context);
        $ttl = now()->addMinutes((int) config('ai.suggestions.cache_ttl_minutes'));

        $payload = Cache::remember($cacheKey, $ttl, function () use ($trip, $query, $prefs, $prefsHash, $context) {
            $candidates = $this->candidateProvider->getCandidates($trip, $query, $prefs, $context);

            if (empty($candidates)) {
                return [
                    'items' => [],
                    'meta' => $this->meta($trip, $query, $prefsHash, $context, true),
                ];
            }

            $ai = $this->reasoner->rankAndExplain($candidates, $prefs, $context, $query->locale);

            $items = [];
            foreach ($candidates as $idx => $c) {
                $aiRow = $ai[$idx] ?? [
                    'score' => 0.0,
                    'reason' => 'Recommended based on your trip context.',
                    'estimated_visit_minutes' => null,
                ];

                $canonical = $c['category'] ?? 'other';

                // Hard safety: never recommend technical categories
                if (!$this->categories->isRecommendable((string) $canonical)) {
                    continue;
                }

                $items[] = new SuggestedPlace(
                    source: (string) $c['source'],
                    internalPlaceId: $c['internal_place_id'] ?? null,
                    externalId: $c['external_id'] ?? null,

                    name: (string) $c['name'],
                    category: $canonical ? (string) $canonical : null,
                    rating: isset($c['rating']) ? (float) $c['rating'] : null,
                    reviewsCount: array_key_exists('reviews_count', $c) ? ($c['reviews_count'] !== null ? (int) $c['reviews_count'] : null) : null,

                    lat: (float) $c['lat'],
                    lon: (float) $c['lon'],
                    distanceMeters: isset($c['distance_m']) ? (int) $c['distance_m'] : null,

                    estimatedVisitMinutes: isset($aiRow['estimated_visit_minutes']) ? (int) $aiRow['estimated_visit_minutes'] : null,
                    score: max(0.0, min(1.0, (float) ($aiRow['score'] ?? 0.0))),
                    reason: (string) ($aiRow['reason'] ?? 'Recommended based on your trip context.'),

                    addPayload: $this->buildAddPayload($c),
                );
            }

            usort($items, fn (SuggestedPlace $a, SuggestedPlace $b) => $b->score <=> $a->score);

            $items = $this->applyQualityFilters($items);

            $items = array_slice($items, 0, $query->limit);

            $this->activityLogger->add(
                actor: auth()->user(),
                action: 'trip.place_suggestions_generated',
                target: $trip,
                details: [
                    'trip_id' => $trip->id,
                    'based_on_place_id' => $query->basedOnPlaceId,
                    'count' => count($items),
                    'radius_m' => $query->radiusMeters,
                    'prefs_hash' => Str::substr($prefsHash, 0, 16),
                    'origin' => $context['origin'] ?? null,
                ]
            );

            return [
                'items' => $items,
                'meta' => $this->meta($trip, $query, $prefsHash, $context, empty($items)),
            ];
        });

        return new SuggestedPlaceCollection(items: $payload['items'], meta: $payload['meta']);
    }

    private function clampQuery(PlaceSuggestionQuery $query): PlaceSuggestionQuery
    {
        $limit = max(1, min((int) config('ai.suggestions.max_limit'), $query->limit));
        $radius = max((int) config('ai.suggestions.min_radius_m'), min((int) config('ai.suggestions.max_radius_m'), $query->radiusMeters));

        return new PlaceSuggestionQuery(
            basedOnPlaceId: $query->basedOnPlaceId,
            limit: $limit,
            radiusMeters: $radius,
            locale: $query->locale ?: 'en',
        );
    }

    private function buildContext(Trip $trip, PlaceSuggestionQuery $query): array
    {
        $origin = null;

        if ($query->basedOnPlaceId) {
            $place = Place::query()->find($query->basedOnPlaceId);
            if ($place) {
                $origin = $this->placeCoords($place);
            }
        }

        if (!$origin && $trip->start_latitude && $trip->start_longitude) {
            $origin = [
                'lat' => (float) $trip->start_latitude,
                'lon' => (float) $trip->start_longitude,
            ];
        }

        return [
            'origin' => $origin,
            'radius_m' => $query->radiusMeters,
        ];
    }

    private function placeCoords(Place $place): array
    {
        if (method_exists($place, 'getAttribute') && $place->getAttribute('lat') !== null && $place->getAttribute('lon') !== null) {
            return ['lat' => (float) $place->getAttribute('lat'), 'lon' => (float) $place->getAttribute('lon')];
        }

        return [
            'lat' => (float) ($place->latitude ?? 0),
            'lon' => (float) ($place->longitude ?? 0),
        ];
    }

    private function applyQualityFilters(array $items): array
    {
        $minScore = (float) config('ai.suggestions.quality.min_score');
        $strict = (bool) config('ai.suggestions.quality.strict');

        if ($strict) {
            $minRating = (float) config('ai.suggestions.quality.min_rating');
            $minReviews = (int) config('ai.suggestions.quality.min_reviews');

            return array_values(array_filter($items, function (SuggestedPlace $p) use ($minScore, $minRating, $minReviews) {
                if ($p->score < $minScore) return false;
                if ($p->rating !== null && $p->rating < $minRating) return false;
                if ($p->reviewsCount !== null && $p->reviewsCount < $minReviews) return false;

                return true;
            }));
        }

        return array_values(array_filter($items, fn (SuggestedPlace $p) => $p->score >= $minScore));
    }

    private function cacheKey(int $tripId, PlaceSuggestionQuery $query, string $prefsHash, array $context): string
    {
        $origin = $context['origin'] ?? null;

        return 'ai:suggestions:trip:' . $tripId
            . ':basedOn=' . ($query->basedOnPlaceId ?? 'none')
            . ':limit=' . $query->limit
            . ':radius=' . $query->radiusMeters
            . ':locale=' . $query->locale
            . ':prefs=' . Str::substr($prefsHash, 0, 24)
            . ':origin=' . ($origin ? ($origin['lat'] . ',' . $origin['lon']) : 'none');
    }

    private function meta(Trip $trip, PlaceSuggestionQuery $query, string $prefsHash, array $context, bool $empty): array
    {
        return [
            'trip_id' => $trip->id,
            'based_on_place_id' => $query->basedOnPlaceId,
            'radius_m' => $query->radiusMeters,
            'limit' => $query->limit,
            'locale' => $query->locale,
            'prefs_hash' => Str::substr($prefsHash, 0, 16),
            'origin' => $context['origin'] ?? null,
            'empty' => $empty,
            'cached' => true,
        ];
    }

    private function buildAddPayload(array $candidate): array
    {
        if (($candidate['source'] ?? null) === 'internal_db' && isset($candidate['internal_place_id'])) {
            return [
                'source' => 'internal_db',
                'place_id' => (int) $candidate['internal_place_id'],
            ];
        }

        return [
            'source' => (string) ($candidate['source'] ?? 'external'),
            'external_id' => $candidate['external_id'] ?? null,
            'name' => (string) $candidate['name'],
            'category' => $candidate['category'] ?? null,
            'rating' => $candidate['rating'] ?? null,
            'reviews_count' => $candidate['reviews_count'] ?? null,
            'lat' => (float) $candidate['lat'],
            'lon' => (float) $candidate['lon'],
        ];
    }
}
