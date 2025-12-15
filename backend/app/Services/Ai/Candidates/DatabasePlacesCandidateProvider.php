<?php

namespace App\Services\Ai\Candidates;

use App\DTO\Ai\PlaceSuggestionQuery;
use App\Interfaces\Ai\PlacesCandidateProviderInterface;
use App\Models\Place;
use App\Models\Trip;
use App\Services\Ai\CategoryNormalizer;
use App\Services\External\GooglePlacesService;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class DatabasePlacesCandidateProvider implements PlacesCandidateProviderInterface
{
    private const RECOMMENDABLE = ['food', 'nightlife', 'museum', 'nature', 'attraction'];

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

        $preferred = $this->extractPreferredFromWeightsMap($preferences);
        $preferred = array_values(array_unique(array_intersect($preferred, self::RECOMMENDABLE)));

        if (empty($preferred)) {
            return [];
        }

        $existingPlaceIds = DB::table('trip_place')
            ->where('trip_id', $trip->id)
            ->pluck('place_id')
            ->all();

        $dbCandidates = $this->getDbCandidates($origin, $preferred, $query, $existingPlaceIds);

        if (!config('ai.suggestions.external.enabled')) {
            return $dbCandidates;
        }

        $googleCandidates = $this->getGoogleCandidates($trip, $origin, $preferred, $query);

        return $this->mergePreferDb($dbCandidates, $googleCandidates);
    }

    private function getDbCandidates(array $origin, array $preferred, PlaceSuggestionQuery $query, array $existingPlaceIds): array
    {
        $limit = max(1, min((int) config('ai.suggestions.max_limit'), $query->limit * 12));
        $pointGeog = "ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography";

        $qb = Place::query()
            ->select([
                'places.id',
                'places.name',
                'places.category_slug',
                'places.rating',
                'places.meta',
                'places.google_place_id',
            ])
            ->selectRaw('ST_Y(places.location::geometry) as lat')
            ->selectRaw('ST_X(places.location::geometry) as lon')
            ->selectRaw("ST_Distance(places.location, $pointGeog) as distance_m", [(float) $origin['lon'], (float) $origin['lat']])
            ->whereNotNull('places.location')
            ->whereIn('places.category_slug', $preferred)
            ->whereRaw("ST_DWithin(places.location, $pointGeog, ?)", [(float) $origin['lon'], (float) $origin['lat'], (int) $query->radiusMeters])
            ->orderBy('distance_m')
            ->limit($limit);

        if (!empty($existingPlaceIds)) {
            $qb->whereNotIn('places.id', $existingPlaceIds);
        }

        $rows = $qb->get();

        $out = [];
        foreach ($rows as $row) {
            $canonical = $row->category_slug ? (string) $row->category_slug : 'other';
            if (!in_array($canonical, self::RECOMMENDABLE, true)) {
                continue;
            }

            $reviewsCount = $this->extractReviewsCount($row->meta);

            $out[] = [
                'source' => 'internal_db',
                'internal_place_id' => (int) $row->id,
                'external_id' => $row->google_place_id ? ('google:' . $row->google_place_id) : null,

                'name' => (string) $row->name,
                'category' => $canonical,
                'rating' => $row->rating !== null ? (float) $row->rating : null,
                'reviews_count' => $reviewsCount,

                'lat' => (float) $row->lat,
                'lon' => (float) $row->lon,
                'distance_m' => $row->distance_m !== null ? (int) round($row->distance_m) : null,
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
            limit: (int) config('ai.suggestions.external.max_candidates')
        );

        // Do not suggest places already stored in DB
        $existingGoogleIds = Place::query()
            ->whereNotNull('google_place_id')
            ->pluck('google_place_id')
            ->all();

        $originLat = (float) $origin['lat'];
        $originLon = (float) $origin['lon'];

        $external = [];
        foreach ($raw as $p) {
            $pid = $p['place_id'] ?? null;
            if (!$pid || in_array($pid, $existingGoogleIds, true)) {
                continue;
            }

            $lat = isset($p['lat']) ? (float) $p['lat'] : null;
            $lon = isset($p['lon']) ? (float) $p['lon'] : null;
            if ($lat === null || $lon === null) {
                continue;
            }

            $canonical = $this->canonicalFromGooglePayload($p);
            if (!$canonical) {
                continue;
            }

            if (!in_array($canonical, self::RECOMMENDABLE, true) || !in_array($canonical, $preferred, true)) {
                continue;
            }

            $external[] = [
                'source' => 'google',
                'internal_place_id' => null,
                'external_id' => 'google:' . $pid,

                'name' => (string) ($p['name'] ?? 'Unknown place'),
                'category' => $canonical,
                'rating' => isset($p['rating']) ? (float) $p['rating'] : null,
                'reviews_count' => (int) Arr::get($p, 'meta.user_ratings_total', 0),

                'lat' => $lat,
                'lon' => $lon,
                'distance_m' => $this->haversineMeters($originLat, $originLon, $lat, $lon),

                'meta' => $p['meta'] ?? [],
            ];
        }

        return $external;
    }

    private function canonicalFromGooglePayload(array $p): ?string
    {
        // 1) Prefer matching by types list
        $types = (array) Arr::get($p, 'meta.types', []);
        foreach ($types as $t) {
            if (!is_string($t)) continue;

            $canonical = $this->categories->normalize($t);
            if ($this->categories->isRecommendable($canonical)) {
                return $canonical;
            }
        }

        // 2) Fallback: the "category_slug" returned by GooglePlacesService is a Google type
        $type = $p['category_slug'] ?? null;
        if (is_string($type)) {
            $canonical = $this->categories->normalize($type);
            if ($this->categories->isRecommendable($canonical)) {
                return $canonical;
            }
        }

        return null;
    }

    private function mergePreferDb(array $db, array $google): array
    {
        // Prefer DB entries when both have same external_id (google place id)
        $dbByExternal = collect($db)
            ->filter(fn (array $x) => !empty($x['external_id']))
            ->keyBy('external_id');

        $merged = $db;

        foreach ($google as $g) {
            $eid = $g['external_id'] ?? null;
            if ($eid && $dbByExternal->has($eid)) {
                continue;
            }
            $merged[] = $g;
        }

        // Dedupe by external_id, otherwise by internal id
        return array_values(collect($merged)->unique(function (array $x) {
            return $x['external_id'] ?? ('internal:' . ($x['internal_place_id'] ?? '0'));
        })->values()->all());
    }

    private function extractPreferredFromWeightsMap(array $preferences): array
    {
        $cats = [];
        foreach ($preferences as $key => $weight) {
            if (!is_string($key) || !is_numeric($weight)) {
                continue;
            }
            if ((float) $weight > 0.0) {
                $cats[] = strtolower($key);
            }
        }
        return $cats;
    }

    private function extractReviewsCount(mixed $meta): ?int
    {
        if (is_string($meta)) {
            $decoded = json_decode($meta, true);
            if (is_array($decoded)) {
                $meta = $decoded;
            }
        }

        if (is_array($meta)) {
            $v = $meta['user_ratings_total'] ?? $meta['reviews_count'] ?? null;
            if ($v === null) return null;
            if (is_numeric($v)) return (int) $v;
        }

        return null;
    }

    private function haversineMeters(float $lat1, float $lon1, float $lat2, float $lon2): int
    {
        $r = 6371000.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) ** 2 +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
            sin($dLon / 2) ** 2;

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return (int) round($r * $c);
    }
}
