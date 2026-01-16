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
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

final class AiPlaceAdvisorService implements AiPlaceAdvisorInterface
{
    public function __construct(
        private readonly PreferenceAggregatorServiceInterface $preferences,
        private readonly PlacesCandidateProviderInterface $candidateProvider,
        private readonly AiPlaceReasonerInterface $reasoner,
        private readonly ActivityLogger $activityLogger,
        private readonly CategoryNormalizer $categories,
        private readonly GeminiEnhancerService $aiEnhancer,
    ) {}

    public function suggestForTrip(Trip $trip, PlaceSuggestionQuery $query): SuggestedPlaceCollection
    {
        if (!config('ai.suggestions.enabled')) {
            return new SuggestedPlaceCollection(items: [], meta: ['trip_id' => $trip->id, 'disabled' => true]);
        }

        $query = $this->clampQuery($query);
        $prefs = $this->preferences->getGroupPreferences($trip);
        $prefsHash = hash('sha256', json_encode($prefs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        $context = $this->buildContext($trip, $query);

        $cacheKey = $this->cacheKey($trip->id, $query, $prefsHash, $context);
        $ttl = now()->addMinutes((int) config('ai.suggestions.cache_ttl_minutes'));

        $payload = Cache::remember($cacheKey, $ttl, function () use ($trip, $query, $prefs, $prefsHash, $context) {

            if (empty($context['origin'])) {
                return [
                    'items' => [],
                    'meta' => array_merge($this->meta($trip, $query, $prefsHash, $context, true), ['warning' => 'Location not determined']),
                ];
            }

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
                $aiRow = $ai[$idx] ?? ['score' => 0.0, 'reason' => 'Context match.'];
                $canonical = $c['category'] ?? 'other';

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
                    reviewsCount: $c['reviews_count'] ?? null,
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

            $this->enhanceTopItemsWithGemini($items, $trip, $prefs, $query->locale);

            $items = $this->applyQualityFilters($items);
            $items = array_slice($items, 0, $query->limit);

            return [
                'items' => $items,
                'meta' => $this->meta($trip, $query, $prefsHash, $context, empty($items)),
            ];
        });

        return new SuggestedPlaceCollection(items: $payload['items'], meta: $payload['meta']);
    }

    private function buildContext(Trip $trip, PlaceSuggestionQuery $query): array
    {
        if ($query->basedOnPlaceId) {
            $place = Place::find($query->basedOnPlaceId);
            if ($place) {
                $coords = $this->placeCoords($place);
                if ($this->isValidCoordinate($coords)) {
                    return [
                        'origin' => $coords,
                        'origin_source' => 'manual_place_id',
                        'radius_m' => $query->radiusMeters,
                    ];
                }
            }
        }

        try {
            $lastPlace = DB::table('trip_place')
                ->join('places', 'trip_place.place_id', '=', 'places.id')
                ->where('trip_place.trip_id', $trip->id)
                ->whereNotNull('places.location')
                ->orderBy('trip_place.id', 'desc')
                ->selectRaw('ST_AsText(places.location) as location_text')
                ->first();

            if ($lastPlace && !empty($lastPlace->location_text)) {
                if (preg_match('/POINT\s*\(\s*(-?[0-9\.]+)[,\s]+(-?[0-9\.]+)\s*\)/i', $lastPlace->location_text, $matches)) {
                    $lon = (float) $matches[1];
                    $lat = (float) $matches[2];

                    if (abs($lat) > 0.0001 && abs($lon) > 0.0001) {
                        return [
                            'origin' => ['lat' => $lat, 'lon' => $lon],
                            'origin_source' => 'last_added_place',
                            'radius_m' => $query->radiusMeters,
                        ];
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::error('AI BuildContext Failed: ' . $e->getMessage());
        }

        if ($trip->start_latitude && $trip->start_longitude) {
            return [
                'origin' => ['lat' => (float) $trip->start_latitude, 'lon' => (float) $trip->start_longitude],
                'origin_source' => 'trip_start_location',
                'radius_m' => $query->radiusMeters,
            ];
        }

        if ($trip->destination_latitude && $trip->destination_longitude) {
            return [
                'origin' => ['lat' => (float) $trip->destination_latitude, 'lon' => (float) $trip->destination_longitude],
                'origin_source' => 'trip_destination',
                'radius_m' => $query->radiusMeters,
            ];
        }

        $searchQuery = $trip->destination ?: $trip->name;
        if ($searchQuery) {
            $foundCoords = $this->fetchCoordinatesFromGoogle($searchQuery);
            if ($foundCoords) {
                return [
                    'origin' => $foundCoords,
                    'origin_source' => 'geocoded_text_search',
                    'radius_m' => $query->radiusMeters ?: 5000,
                ];
            }
        }

        return [
            'origin' => null,
            'origin_source' => 'none',
            'radius_m' => $query->radiusMeters,
        ];
    }

    private function clampQuery(PlaceSuggestionQuery $query): PlaceSuggestionQuery
    {
        $limit = max(1, min((int) config('ai.suggestions.max_limit', 20), $query->limit));
        $minR = (int) config('ai.suggestions.min_radius_m', 200);
        $maxR = (int) config('ai.suggestions.max_radius_m', 50000);
        $radius = max($minR, min($maxR, $query->radiusMeters));
        return new PlaceSuggestionQuery($query->basedOnPlaceId, $limit, $radius, $query->locale ?: 'en');
    }

    private function fetchCoordinatesFromGoogle(string $query): ?array
    {
        $apiKey = config('services.google.places.key');
        if (!$apiKey) return null;
        try {
            $response = Http::get('https://maps.googleapis.com/maps/api/place/textsearch/json', [
                'query' => $query,
                'key' => $apiKey,
                'language' => 'en'
            ]);
            if ($response->successful()) {
                $data = $response->json();
                if (!empty($data['results'][0]['geometry']['location'])) {
                    $loc = $data['results'][0]['geometry']['location'];
                    return ['lat' => (float) $loc['lat'], 'lon' => (float) $loc['lng']];
                }
            }
        } catch (\Throwable $e) {}
        return null;
    }

    private function placeCoords(Place $place): array
    {
        $loc = $place->location;
        if (is_string($loc)) {
            if (preg_match('/POINT\s*\(\s*(-?[0-9\.]+)[,\s]+(-?[0-9\.]+)\s*\)/i', $loc, $matches)) {
                return ['lat' => (float) $matches[2], 'lon' => (float) $matches[1]];
            }
        }
        return ['lat' => 0.0, 'lon' => 0.0];
    }

    private function isValidCoordinate(array $coords): bool
    {
        return abs($coords['lat']) > 0.0001 || abs($coords['lon']) > 0.0001;
    }

    private function applyQualityFilters(array $items): array
    {
        $minScore = (float) config('ai.suggestions.quality.min_score');
        return array_values(array_filter($items, fn (SuggestedPlace $p) => $p->score >= $minScore));
    }

    private function cacheKey(int $tripId, PlaceSuggestionQuery $query, string $prefsHash, array $context): string
    {
        $origin = $context['origin'] ?? null;
        return 'ai:suggestions:v16:trip:' . $tripId .
            ':lang:' . $query->locale .
            ':src=' . ($context['origin_source'] ?? 'none') .
            ':orig=' . ($origin ? round($origin['lat'],4).','.round($origin['lon'],4) : 'x') .
            ':p=' . Str::substr($prefsHash, 0, 10);
    }

    private function meta(Trip $trip, PlaceSuggestionQuery $query, string $prefsHash, array $context, bool $empty): array
    {
        return ['trip_id' => $trip->id, 'origin_source' => $context['origin_source'] ?? 'unknown', 'radius_m' => $query->radiusMeters, 'empty' => $empty];
    }

    private function buildAddPayload(array $candidate): array
    {
        if (($candidate['source'] ?? null) === 'internal_db' && isset($candidate['internal_place_id'])) {
            return ['source' => 'internal_db', 'place_id' => (int) $candidate['internal_place_id']];
        }
        return [
            'source' => (string) ($candidate['source'] ?? 'external'),
            'external_id' => $candidate['external_id'] ?? null,
            'name' => (string) $candidate['name'],
            'category' => $candidate['category'] ?? null,
            'rating' => $candidate['rating'] ?? null,
            'lat' => (float) $candidate['lat'],
            'lon' => (float) $candidate['lon'],
        ];
    }

    private function enhanceTopItemsWithGemini(array &$items, Trip $trip, array $prefs, string $locale): void
    {
        $topCandidates = array_slice($items, 0, 5);
        $payloadForAi = [];
        foreach ($topCandidates as $item) {
            $id = (string)($item->externalId ?: $item->internalPlaceId);
            $payloadForAi[] = [
                'external_id' => $id,
                'name' => $item->name,
                'category' => $item->category,
                'distance' => $item->distanceMeters,
                'rating' => $item->rating
            ];
        }
        if (empty($payloadForAi)) return;

        $tripContext = $trip->destination ? "Trip to {$trip->destination}" : "Trip ID: {$trip->id}";
        $rawAiReasons = $this->aiEnhancer->enhancePlaces($payloadForAi, $prefs, $tripContext, $locale);

        $aiReasons = [];
        foreach ($rawAiReasons as $k => $v) {
            $cleanK = strtolower(trim(str_replace(['google:', 'internal:'], '', (string)$k)));
            $aiReasons[$cleanK] = $v;
        }

        $fallbacks = [
            'en' => 'Recommended based on your preferences and location.',
            'pl' => 'Polecane na podstawie Twoich preferencji и lokalizacji.'
        ];
        $defaultReason = $fallbacks[$locale] ?? $fallbacks['en'];

        foreach ($items as $idx => $item) {
            $originalKey = (string)($item->externalId ?: $item->internalPlaceId);
            $cleanKey = strtolower(trim(str_replace(['google:', 'internal:'], '', $originalKey)));
            $newReason = $aiReasons[$cleanKey] ?? null;

            if ($newReason || $item->reason === 'Loading AI recommendation...') {
                $items[$idx] = new SuggestedPlace(
                    source: $item->source,
                    internalPlaceId: $item->internalPlaceId,
                    externalId: $item->externalId,
                    name: $item->name,
                    category: $item->category,
                    rating: $item->rating,
                    reviewsCount: $item->reviewsCount,
                    lat: $item->lat,
                    lon: $item->lon,
                    distanceMeters: $item->distanceMeters,
                    estimatedVisitMinutes: $item->estimatedVisitMinutes,
                    score: $item->score,
                    reason: (string)($newReason ?? $defaultReason),
                    addPayload: $item->addPayload
                );
            }
        }
    }
}
