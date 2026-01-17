<?php

namespace App\Services\Ai;

use App\DTO\Ai\PlaceSuggestionQuery;
use App\DTO\Ai\SuggestedPlace;
use App\DTO\Ai\SuggestedPlaceCollection;
use App\Interfaces\Ai\AiPlaceAdvisorInterface;
use App\Interfaces\Ai\AiPlaceReasonerInterface;
use App\Interfaces\Ai\PlacesCandidateProviderInterface;
use App\Interfaces\PreferenceAggregatorServiceInterface;
use App\Models\Trip;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class AiPlaceAdvisorService implements AiPlaceAdvisorInterface
{
    public function __construct(
        private readonly PreferenceAggregatorServiceInterface $preferences,
        private readonly PlacesCandidateProviderInterface $candidateProvider,
        private readonly AiPlaceReasonerInterface $reasoner,
        private readonly CategoryNormalizer $categories,
        private readonly GeminiEnhancerService $aiEnhancer,
    ) {}

    public function suggestForTrip(Trip $trip, PlaceSuggestionQuery $query): SuggestedPlaceCollection
    {
        if (!(bool) config('ai.suggestions.enabled')) {
            return new SuggestedPlaceCollection(items: [], meta: ['trip_id' => $trip->id]);
        }

        $query = $this->clampQuery($query);

        $prefs = $this->preferences->getGroupPreferences($trip);
        $prefsHash = hash('sha256', json_encode($prefs));

        $context = $this->buildContext($trip, $query);

        $cacheKey = sprintf(
            'ai:sug:v22:trip:%d:%s:l:%s:r:%d:n:%d',
            (int) $trip->id,
            Str::substr($prefsHash, 0, 8),
            $query->locale,
            (int) $query->radiusMeters,
            (int) $query->limit
        );

        $payload = Cache::remember($cacheKey, now()->addHours(12), function () use ($trip, $query, $prefs, $context) {
            if (empty($context['origins'])) {
                return ['items' => [], 'meta' => ['trip_id' => $trip->id, 'empty' => true, 'origin_source' => $context['origin_source'] ?? 'none']];
            }

            $candidates = $this->candidateProvider->getCandidates($trip, $query, $prefs, $context);
            if (empty($candidates)) {
                return ['items' => [], 'meta' => ['trip_id' => $trip->id, 'empty' => true, 'origin_source' => $context['origin_source'] ?? 'none']];
            }

            $aiRows = $this->reasoner->rankAndExplain($candidates, $prefs, $context, $query->locale);

            $items = [];
            foreach ($candidates as $idx => $c) {
                $aiRow = $aiRows[$idx] ?? ['score' => 0.0, 'reason' => 'Match.'];

                $canonical = (string) ($c['category'] ?? 'other');
                if (!$this->categories->isRecommendable($canonical)) {
                    continue;
                }

                $externalId = $c['external_id'] ?? null;
                $googlePlaceId = is_string($externalId) ? str_replace('google:', '', $externalId) : null;

                $items[] = new SuggestedPlace(
                    source: (string) ($c['source'] ?? 'unknown'),
                    internalPlaceId: $c['internal_place_id'] ?? null,
                    externalId: $externalId,
                    name: (string) ($c['name'] ?? ''),
                    category: $canonical,
                    rating: isset($c['rating']) ? (float) $c['rating'] : null,
                    reviewsCount: (int) ($c['reviews_count'] ?? 0),
                    lat: (float) ($c['lat'] ?? 0),
                    lon: (float) ($c['lon'] ?? 0),
                    distanceMeters: (int) ($c['distance_m'] ?? 0),
                    nearPlaceName: $c['near_place_name'] ?? null,
                    estimatedVisitMinutes: 60,
                    score: (float) ($aiRow['score'] ?? 0.0),
                    reason: (string) ($aiRow['reason'] ?? 'Match.'),
                    addPayload: [
                        'source' => $c['source'] ?? 'unknown',
                        'place_id' => $c['internal_place_id'] ?? null,
                        'google_place_id' => $googlePlaceId,
                        'name' => $c['name'] ?? null,
                    ],
                );
            }

            usort($items, static fn ($a, $b) => $b->score <=> $a->score);

            $this->enhanceTopItemsWithGemini($items, $trip, $prefs, $query->locale);

            $minScore = (float) config('ai.suggestions.quality.min_score', 0.1);
            $items = array_values(array_filter($items, static fn ($i) => $i->score >= $minScore));

            return [
                'items' => array_slice($items, 0, (int) $query->limit),
                'meta' => [
                    'trip_id' => (int) $trip->id,
                    'origin_source' => $context['origin_source'] ?? 'none',
                    'empty' => empty($items),
                ],
            ];
        });

        return new SuggestedPlaceCollection(items: $payload['items'], meta: $payload['meta']);
    }

    private function buildContext(Trip $trip, PlaceSuggestionQuery $query): array
    {
        $points = DB::table('trip_place')
            ->join('places', 'trip_place.place_id', '=', 'places.id')
            ->where('trip_place.trip_id', $trip->id)
            ->whereNotNull('places.location')
            ->selectRaw('places.name, ST_Y(places.location::geometry) as lat, ST_X(places.location::geometry) as lon')
            ->get()
            ->map(static fn ($p) => [
                'name' => (string) ($p->name ?? ''),
                'lat' => (float) ($p->lat ?? 0),
                'lon' => (float) ($p->lon ?? 0),
            ])
            ->all();

        if (!empty($points)) {
            return [
                'origins' => $points,
                'origin_source' => 'all_trip_places',
                'radius_m' => (int) $query->radiusMeters,
            ];
        }

        if (!empty($trip->start_latitude)) {
            return [
                'origins' => [[
                    'name' => (string) $trip->name . ' (Start)',
                    'lat' => (float) $trip->start_latitude,
                    'lon' => (float) ($trip->start_longitude ?? 0),
                ]],
                'origin_source' => 'trip_start',
                'radius_m' => (int) $query->radiusMeters,
            ];
        }

        return ['origins' => [], 'origin_source' => 'none', 'radius_m' => (int) $query->radiusMeters];
    }

    private function clampQuery(PlaceSuggestionQuery $query): PlaceSuggestionQuery
    {
        $limit = max(1, min(20, (int) $query->limit));
        $radius = (int) ($query->radiusMeters ?: 10000);
        $locale = (string) ($query->locale ?: 'en');

        return new PlaceSuggestionQuery($query->basedOnPlaceId, $limit, $radius, $locale);
    }

    private function enhanceTopItemsWithGemini(array &$items, Trip $trip, array $prefs, string $locale): void
    {
        $top = array_slice($items, 0, 10);
        if (empty($top)) {
            return;
        }

        $payload = collect($top)->map(static function ($i) {
            $id = $i->externalId ?: ('internal:' . (string) $i->internalPlaceId);

            return [
                'external_id' => (string) $id,
                'name' => (string) $i->name,
                'category' => (string) $i->category,
            ];
        })->all();

        $tripContext = !empty($trip->destination) ? "Trip to {$trip->destination}" : "Trip #{$trip->id}";
        $reasons = $this->aiEnhancer->enhancePlaces($payload, $prefs, $tripContext, $locale);

        foreach ($items as $idx => $item) {
            $rawKey = (string) ($item->externalId ?: ('internal:' . (string) $item->internalPlaceId));
            $key = strtolower(trim(str_replace(['google:', 'internal:'], '', $rawKey)));

            if (isset($reasons[$key]) && is_string($reasons[$key]) && $reasons[$key] !== '') {
                $items[$idx]->reason = $reasons[$key];
            }
        }
    }
}
