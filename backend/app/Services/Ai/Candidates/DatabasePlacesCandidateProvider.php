<?php

namespace App\Services\Ai\Candidates;

use App\DTO\Ai\PlaceSuggestionQuery;
use App\Interfaces\Ai\PlacesCandidateProviderInterface;
use App\Models\Place;
use App\Models\Trip;
use App\Services\Ai\CategoryNormalizer;
use App\Services\External\GooglePlacesService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class DatabasePlacesCandidateProvider implements PlacesCandidateProviderInterface
{
    public function __construct(
        private readonly CategoryNormalizer $categories,
        private readonly GooglePlacesService $googlePlaces,
    ) {}

    public function getCandidates(Trip $trip, PlaceSuggestionQuery $query, array $preferences, array $context): array
    {
        $origins = $context['origins'] ?? [];
        if (empty($origins)) return [];

        $preferred = array_keys(array_filter($preferences, fn($v) => (float)$v > 0));
        $existingPlaceIds = DB::table('trip_place')->where('trip_id', $trip->id)->pluck('place_id')->all();

        // 1. Поиск в локальной базе
        $dbCandidates = $this->getDbCandidates($origins, $preferred, $query->radiusMeters, $existingPlaceIds);

        // 2. Если результатов мало, опрашиваем Google Places
        if (count($dbCandidates) < $query->limit && config('ai.suggestions.external.enabled')) {
            foreach ($origins as $origin) {
                try {
                    $googleResults = $this->getGoogleCandidates($trip, $origin, $preferred, $query);
                    $dbCandidates = $this->mergeResults($dbCandidates, $googleResults);
                } catch (\Exception $e) {
                    Log::error("Google search failed for origin: {$origin['name']}");
                }
            }
        }

        return $dbCandidates;
    }

    private function getDbCandidates(array $origins, array $categories, int $radius, array $existingPlaceIds): array
    {
        $pointsSql = collect($origins)->map(fn($p) => "{$p['lon']} {$p['lat']}")->implode(',');
        $multiPoint = "ST_GeogFromText('MULTIPOINT($pointsSql)')";

        $results = Place::query()
            ->select(['id', 'name', 'category_slug', 'rating', 'meta', 'google_place_id'])
            ->selectRaw('ST_Y(location::geometry) as lat, ST_X(location::geometry) as lon')
            ->whereRaw("ST_DWithin(location, $multiPoint, ?)", [$radius])
            ->whereNotNull('location')
            ->orderBy('rating', 'desc')
            ->limit(50);

        if (!empty($categories)) {
            $results->whereIn('category_slug', $categories);
        }

        if (!empty($existingPlaceIds)) {
            $results->whereNotIn('id', $existingPlaceIds);
        }

        return $results->get()->map(function($row) use ($origins) {
            $lat = (float)$row->lat;
            $lon = (float)$row->lon;
            $closest = $this->findNearestOrigin($lat, $lon, $origins);

            return [
                'source' => 'internal_db',
                'internal_place_id' => (int) $row->id,
                'external_id' => $row->google_place_id ? 'google:' . $row->google_place_id : null,
                'name' => (string) $row->name,
                'category' => $row->category_slug ?? 'other',
                'rating' => (float) $row->rating,
                'reviews_count' => $this->extractReviews($row->meta), // Исправленный вызов
                'lat' => $lat,
                'lon' => $lon,
                'distance_m' => $closest['dist'],
                'near_place_name' => $closest['name']
            ];
        })->all();
    }

    private function getGoogleCandidates(Trip $trip, array $origin, array $preferred, $query): array
    {
        $raw = $this->googlePlaces->fetchNearbyForTripByPreferredCategories($trip, $preferred, $query->radiusMeters, 15);
        return collect($raw)->map(fn($p) => [
            'source' => 'google',
            'external_id' => 'google:' . $p['place_id'],
            'name' => (string) ($p['name'] ?? 'Unknown'),
            'category' => $this->categories->normalize($p['category_slug'] ?? 'other'),
            'rating' => (float) ($p['rating'] ?? 0),
            'reviews_count' => (int) ($p['user_ratings_total'] ?? 0),
            'lat' => (float) $p['lat'],
            'lon' => (float) $p['lon'],
            'distance_m' => $this->haversineMeters($origin['lat'], $origin['lon'], (float)$p['lat'], (float)$p['lon']),
            'near_place_name' => $origin['name']
        ])->all();
    }

    /**
     * Безопасное извлечение отзывов, даже если meta — это строка или null
     */
    private function extractReviews(mixed $meta): int
    {
        if (empty($meta)) return 0;

        // Если meta пришла как JSON-строка (бывает в некоторых драйверах)
        if (is_string($meta)) {
            $meta = json_decode($meta, true);
        }

        if (!is_array($meta)) return 0;

        return (int) ($meta['user_ratings_total'] ?? $meta['reviews_count'] ?? 0);
    }

    private function findNearestOrigin(float $lat, float $lon, array $origins): array
    {
        $minDist = 9999999;
        $best = ['dist' => 0, 'name' => 'your route'];

        foreach ($origins as $o) {
            $d = $this->haversineMeters($lat, $lon, $o['lat'], $o['lon']);
            if ($d < $minDist) {
                $minDist = $d;
                $best = ['dist' => (int)$d, 'name' => $o['name']];
            }
        }
        return $best;
    }

    private function haversineMeters(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $R = 6371000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;
        return $R * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    private function mergeResults(array $existing, array $new): array
    {
        $existingIds = collect($existing)->pluck('external_id')->filter()->all();
        $filteredNew = collect($new)->reject(fn($item) => in_array($item['external_id'], $existingIds))->all();
        return array_merge($existing, $filteredNew);
    }
}
