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
        'park', 'cafe', 'restaurant', 'zoo', 'aquarium', 'gallery',
        'establishment', 'point_of_interest', 'store', 'other'
    ];

    public function __construct(
        private readonly CategoryNormalizer $categories,
        private readonly GooglePlacesService $googlePlaces,
    ) {}

    public function getCandidates(Trip $trip, PlaceSuggestionQuery $query, array $preferences, array $context): array
    {
        $origin = $context['origin'] ?? null;
        if (!$origin || empty($origin['lat']) || empty($origin['lon'])) return [];

        $preferred = array_values(array_unique(array_intersect(
            array_keys(array_filter($preferences, fn($v) => (float)$v > 0)),
            self::RECOMMENDABLE
        )));

        if (empty($preferred)) $preferred = self::RECOMMENDABLE;

        $existingPlaceIds = DB::table('trip_place')->where('trip_id', $trip->id)->pluck('place_id')->all();

        $dbCandidates = $this->getDbCandidates($origin, $preferred, $query->radiusMeters, $existingPlaceIds);

        if (empty($dbCandidates)) {
            $dbCandidates = $this->getDbCandidates($origin, [], max(20000, $query->radiusMeters * 5), $existingPlaceIds);
        }

        if (config('ai.suggestions.external.enabled') && count($dbCandidates) < $query->limit) {
            try {
                $googleCandidates = $this->getGoogleCandidates($trip, $origin, $preferred, $query);
                return $this->mergePreferDb($dbCandidates, $googleCandidates);
            } catch (\Exception $e) {
                Log::error('AI Google Search Error: ' . $e->getMessage());
            }
        }

        return $dbCandidates;
    }

    private function getDbCandidates(array $origin, array $categories, int $radius, array $existingPlaceIds): array
    {
        $originSql = sprintf("ST_GeogFromText('POINT(%F %F)')", $origin['lon'], $origin['lat']);

        $qb = Place::query()
            ->select(['id', 'name', 'category_slug', 'rating', 'meta', 'google_place_id', 'opening_hours'])
            ->selectRaw('ST_Y(location::geometry) as lat, ST_X(location::geometry) as lon')
            ->selectRaw("ST_Distance(location, $originSql) as distance_m")
            ->whereNotNull('location')
            ->whereRaw("ST_DWithin(location, $originSql, ?)", [$radius])
            ->orderBy('distance_m', 'asc')
            ->limit(50);

        if (!empty($categories)) $qb->whereIn('category_slug', $categories);
        if (!empty($existingPlaceIds)) $qb->whereNotIn('id', $existingPlaceIds);

        return $qb->get()->map(fn($row) => [
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
            'opening_hours' => $row->opening_hours,
        ])->all();
    }

    private function getGoogleCandidates(Trip $trip, array $origin, array $preferred, PlaceSuggestionQuery $query): array
    {
        $raw = $this->googlePlaces->fetchNearbyForTripByPreferredCategories($trip, $preferred, $query->radiusMeters, 20);
        return collect($raw)->map(fn($p) => [
            'source' => 'google',
            'internal_place_id' => null,
            'external_id' => 'google:' . $p['place_id'],
            'name' => (string) ($p['name'] ?? 'Unknown'),
            'category' => $this->canonicalFromGooglePayload($p),
            'rating' => isset($p['rating']) ? (float) $p['rating'] : null,
            'reviews_count' => (int) Arr::get($p, 'meta.user_ratings_total', 0),
            'lat' => (float) $p['lat'],
            'lon' => (float) $p['lon'],
            'distance_m' => $this->haversineMeters((float)$origin['lat'], (float)$origin['lon'], (float)$p['lat'], (float)$p['lon']),
            'opening_hours' => $p['opening_hours'] ?? Arr::get($p, 'meta.opening_hours'),
            'meta' => $p['meta'] ?? [],
        ])->all();
    }

    private function canonicalFromGooglePayload(array $p): string
    {
        foreach ((array)Arr::get($p, 'meta.types', []) as $t) {
            $c = $this->categories->normalize($t);
            if ($this->categories->isRecommendable($c)) return $c;
        }
        return $this->categories->normalize($p['category_slug'] ?? 'other');
    }

    private function mergePreferDb(array $db, array $google): array
    {
        $dbByExternal = collect($db)->filter(fn($x)=>!empty($x['external_id']))->keyBy('external_id');
        $merged = $db;
        foreach ($google as $g) {
            if (!isset($g['external_id']) || !$dbByExternal->has($g['external_id'])) $merged[] = $g;
        }
        return array_values($merged);
    }

    private function extractReviewsCount(mixed $meta): ?int
    {
        if (is_string($meta)) $meta = json_decode($meta, true);
        return $meta['user_ratings_total'] ?? $meta['reviews_count'] ?? null;
    }

    private function haversineMeters(float $lat1, float $lon1, float $lat2, float $lon2): int
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;
        return (int) round(6371000.0 * 2 * atan2(sqrt($a), sqrt(1 - $a)));
    }
}
