<?php

namespace App\Services\Ai\Candidates;

use App\DTO\Ai\PlaceSuggestionQuery;
use App\Interfaces\Ai\PlacesCandidateProviderInterface;
use App\Models\Place;
use App\Models\Trip;
use App\Services\Ai\CategoryNormalizer;
use App\Services\External\GooglePlacesService;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class DatabasePlacesCandidateProvider implements PlacesCandidateProviderInterface
{
    private const RECOMMENDABLE = [
        'food', 'nightlife', 'museum', 'nature', 'attraction',
        'park', 'establishment', 'point_of_interest', 'store', 'cafe', 'restaurant',
        'station', 'transit_station', 'shopping_mall', 'other', 'lodging', 'transport'
    ];

    public function __construct(
        private readonly CategoryNormalizer $categories,
        private readonly GooglePlacesService $googlePlaces,
    ) {}

    public function getCandidates(Trip $trip, PlaceSuggestionQuery $query, array $preferences, array $context): array
    {
        $origin = $context['origin'] ?? null;
        if (!$origin || empty($origin['lat']) || empty($origin['lon'])) {
            return [];
        }

        // 1. Категории
        $preferred = $this->extractPreferredFromWeightsMap($preferences);
        $preferred = array_values(array_unique(array_intersect($preferred, self::RECOMMENDABLE)));
        if (empty($preferred)) {
            $preferred = self::RECOMMENDABLE;
        }

        // 2. Исключаем уже добавленные
        $existingPlaceIds = DB::table('trip_place')
            ->where('trip_id', $trip->id)
            ->pluck('place_id')
            ->all();

        // 3. ПОИСК В БАЗЕ (Основной - узкий радиус, строгие категории)
        $dbCandidates = $this->getDbCandidates($origin, $preferred, $query->radiusMeters, $existingPlaceIds);

        // 4. ПЛАН Б (Если пусто - расширяем радиус до 20 км и убираем фильтр категорий)
        // Это гарантирует, что мы найдем ваши места (расстояние ~5.5км)
        if (empty($dbCandidates)) {
            $expandedRadius = max(20000, $query->radiusMeters * 5); // Минимум 20 км
            $dbCandidates = $this->getDbCandidates($origin, [], $expandedRadius, $existingPlaceIds);
        }

        // 5. GOOGLE (если включено и мало результатов)
        if (config('ai.suggestions.external.enabled') && count($dbCandidates) < $query->limit) {
            try {
                $googleCandidates = $this->getGoogleCandidates($trip, $origin, $preferred, $query);
                return $this->mergePreferDb($dbCandidates, $googleCandidates);
            } catch (\Exception $e) {
                Log::error('AI Google Error: ' . $e->getMessage());
            }
        }

        return $dbCandidates;
    }

    private function getDbCandidates(array $origin, array $categories, int $radius, array $existingPlaceIds): array
    {
        $limit = 50;

        // Используем sprintf для безопасной вставки координат
        $originSql = sprintf("ST_GeogFromText('POINT(%F %F)')", $origin['lon'], $origin['lat']);

        $qb = Place::query()
            ->select([
                'places.id',
                'places.name',
                'places.category_slug',
                'places.rating',
                'places.meta',
                'places.google_place_id',
            ])
            // Выбираем координаты через ST_Y/ST_X
            ->selectRaw('ST_Y(places.location::geometry) as lat')
            ->selectRaw('ST_X(places.location::geometry) as lon')
            ->selectRaw("ST_Distance(places.location, $originSql) as distance_m")
            ->whereNotNull('places.location')
            // Фильтр по радиусу
            ->whereRaw("ST_DWithin(places.location, $originSql, ?)", [$radius])
            ->orderBy('distance_m', 'asc')
            ->limit($limit);

        if (!empty($categories)) {
            $qb->whereIn('places.category_slug', $categories);
        }
        if (!empty($existingPlaceIds)) {
            $qb->whereNotIn('places.id', $existingPlaceIds);
        }

        $rows = $qb->get();

        $out = [];
        foreach ($rows as $row) {
            $out[] = [
                'source' => 'internal_db',
                'internal_place_id' => (int) $row->id,
                'external_id' => $row->google_place_id ? ('google:' . $row->google_place_id) : null,
                'name' => (string) $row->name,
                'category' => $row->category_slug ?? 'other',
                'rating' => $row->rating ? (float) $row->rating : null,
                'reviews_count' => $this->extractReviewsCount($row->meta),
                'lat' => (float) $row->lat,
                'lon' => (float) $row->lon,
                'distance_m' => (int) round($row->distance_m),
            ];
        }

        return $out;
    }

    private function getGoogleCandidates(Trip $trip, array $origin, array $preferred, PlaceSuggestionQuery $query): array
    {
        $raw = $this->googlePlaces->fetchNearbyForTripByPreferredCategories(
            trip: $trip,
            preferredCategorySlugs: $preferred,
            radius: $query->radiusMeters,
            limit: 20
        );
        $originLat = (float) $origin['lat'];
        $originLon = (float) $origin['lon'];

        $external = [];
        foreach ($raw as $p) {
            if (empty($p['place_id']) || empty($p['lat']) || empty($p['lon'])) continue;

            $canonical = $this->canonicalFromGooglePayload($p);
            $external[] = [
                'source' => 'google',
                'internal_place_id' => null,
                'external_id' => 'google:' . $p['place_id'],
                'name' => (string) ($p['name'] ?? 'Unknown'),
                'category' => $canonical ?? 'other',
                'rating' => isset($p['rating']) ? (float) $p['rating'] : null,
                'reviews_count' => (int) Arr::get($p, 'meta.user_ratings_total', 0),
                'lat' => (float) $p['lat'],
                'lon' => (float) $p['lon'],
                'distance_m' => $this->haversineMeters($originLat, $originLon, (float) $p['lat'], (float) $p['lon']),
                'meta' => $p['meta'] ?? [],
            ];
        }
        return $external;
    }

    private function canonicalFromGooglePayload(array $p): ?string {
        $types = (array) Arr::get($p, 'meta.types', []);
        foreach ($types as $t) {
            if (!is_string($t)) continue;
            $c = $this->categories->normalize($t);
            if ($this->categories->isRecommendable($c)) return $c;
        }
        return $this->categories->normalize($p['category_slug'] ?? 'other');
    }

    private function mergePreferDb(array $db, array $google): array {
        $dbByExternal = collect($db)->filter(fn($x)=>!empty($x['external_id']))->keyBy('external_id');
        $merged = $db;
        foreach ($google as $g) {
            if (isset($g['external_id']) && $dbByExternal->has($g['external_id'])) continue;
            $merged[] = $g;
        }
        return array_values($merged);
    }

    private function extractPreferredFromWeightsMap(array $preferences): array {
        $cats = [];
        foreach ($preferences as $key => $weight) {
            if ((float) $weight > 0.0) $cats[] = strtolower($key);
        }
        return $cats;
    }

    private function extractReviewsCount(mixed $meta): ?int {
        if (is_string($meta)) $meta = json_decode($meta, true);
        return $meta['user_ratings_total'] ?? $meta['reviews_count'] ?? null;
    }

    private function haversineMeters(float $lat1, float $lon1, float $lat2, float $lon2): int {
        $r = 6371000.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;
        return (int) round($r * 2 * atan2(sqrt($a), sqrt(1 - $a)));
    }
}
