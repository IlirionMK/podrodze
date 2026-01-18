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
        if (empty($origins)) {
            return [];
        }

        $preferred = array_keys(array_filter($preferences, static fn ($v) => (float) $v > 0));
        $existingPlaceIds = DB::table('trip_place')->where('trip_id', $trip->id)->pluck('place_id')->all();

        $dbCandidates = $this->getDbCandidates($origins, $preferred, (int) $query->radiusMeters, $existingPlaceIds);

        if (count($dbCandidates) < (int) $query->limit && (bool) config('ai.suggestions.external.enabled')) {
            foreach ($origins as $origin) {
                try {
                    $googleResults = $this->getGoogleCandidates($trip, $origin, $preferred, $query);
                    $dbCandidates = $this->mergeResults($dbCandidates, $googleResults);
                } catch (\Throwable $e) {
                    Log::error("Google search failed for origin: " . ($origin['name'] ?? 'unknown') . "; " . $e->getMessage());
                }
            }
        }

        return $dbCandidates;
    }

    private function getDbCandidates(array $origins, array $categories, int $radius, array $existingPlaceIds): array
    {
        $pointsSql = collect($origins)->map(static fn ($p) => "{$p['lon']} {$p['lat']}")->implode(',');
        $multiPoint = "ST_GeogFromText('MULTIPOINT($pointsSql)')";

        $results = Place::query()
            ->select(['id', 'name', 'category_slug', 'rating', 'meta', 'google_place_id'])
            ->selectRaw('ST_Y(location::geometry) as lat, ST_X(location::geometry) as lon')
            ->whereNotNull('location')
            ->whereRaw("ST_DWithin(location, $multiPoint, ?)", [$radius])
            ->orderBy('rating', 'desc')
            ->limit(50);

        if (!empty($categories)) {
            $results->whereIn('category_slug', $categories);
        }

        if (!empty($existingPlaceIds)) {
            $results->whereNotIn('id', $existingPlaceIds);
        }

        return $results->get()->map(function ($row) use ($origins) {
            $lat = (float) $row->lat;
            $lon = (float) $row->lon;
            $closest = $this->findNearestOrigin($lat, $lon, $origins);

            return [
                'source' => 'internal_db',
                'internal_place_id' => (int) $row->id,
                'external_id' => $row->google_place_id ? 'google:' . $row->google_place_id : null,
                'name' => (string) $row->name,
                'category' => $row->category_slug ?? 'other',
                'rating' => (float) $row->rating,
                'reviews_count' => $this->extractReviews($row->meta),
                'lat' => $lat,
                'lon' => $lon,
                'distance_m' => (int) ($closest['dist'] ?? 0),
                'near_place_name' => $closest['name'] ?? null,
            ];
        })->all();
    }

    private function getGoogleCandidates(Trip $trip, array $origin, array $preferred, PlaceSuggestionQuery $query): array
    {
        // ✅ FIX: use origin point instead of resolveTripCoords($trip)
        $raw = $this->googlePlaces->fetchNearbyByPointAndPreferredCategories(
            (float) ($origin['lat'] ?? 0),
            (float) ($origin['lon'] ?? 0),
            $preferred,
            (int) $query->radiusMeters,
            15,
            (string) $query->locale
        );

        return collect($raw)->map(function ($p) use ($origin) {
            return [
                'source' => 'google',
                'external_id' => 'google:' . (string) ($p['place_id'] ?? ''),
                'name' => (string) ($p['name'] ?? 'Unknown'),
                'category' => $this->categories->normalize($p['category_slug'] ?? 'other'),
                'rating' => (float) ($p['rating'] ?? 0),
                'reviews_count' => (int) data_get($p, 'meta.user_ratings_total', $p['user_ratings_total'] ?? 0),
                'lat' => (float) ($p['lat'] ?? 0),
                'lon' => (float) ($p['lon'] ?? 0),
                'distance_m' => (int) $this->haversineMeters(
                    (float) ($origin['lat'] ?? 0),
                    (float) ($origin['lon'] ?? 0),
                    (float) ($p['lat'] ?? 0),
                    (float) ($p['lon'] ?? 0),
                ),
                'near_place_name' => $origin['name'] ?? null,
            ];
        })->filter(static fn ($x) => !empty($x['external_id']))->values()->all();
    }

    private function extractReviews(mixed $meta): int
    {
        if (empty($meta)) {
            return 0;
        }

        if (is_string($meta)) {
            $meta = json_decode($meta, true);
        }

        if (!is_array($meta)) {
            return 0;
        }

        return (int) ($meta['user_ratings_total'] ?? $meta['reviews_count'] ?? 0);
    }

    private function findNearestOrigin(float $lat, float $lon, array $origins): array
    {
        $minDist = PHP_FLOAT_MAX;
        $best = ['dist' => 0, 'name' => 'your route'];

        foreach ($origins as $o) {
            $d = $this->haversineMeters($lat, $lon, (float) $o['lat'], (float) $o['lon']);
            if ($d < $minDist) {
                $minDist = $d;
                $best = ['dist' => (int) $d, 'name' => (string) ($o['name'] ?? 'your route')];
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
        $filteredNew = collect($new)->reject(static fn ($item) => in_array($item['external_id'] ?? null, $existingIds, true))->all();
        return array_merge($existing, $filteredNew);
    }
}
