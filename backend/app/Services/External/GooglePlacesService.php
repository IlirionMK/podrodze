<?php

namespace App\Services\External;

use App\Models\Trip;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class GooglePlacesService
{
    protected string $nearbyUrl  = 'https://maps.googleapis.com/maps/api/place/nearbysearch/json';
    protected string $detailsUrl = 'https://maps.googleapis.com/maps/api/place/details/json';

    /**
     * Default allowed Google place types for generic nearby search.
     *
     * @var array<int, string>
     */
    protected array $defaultTypes = [
        'tourist_attraction', 'museum', 'art_gallery', 'park',
        'cafe', 'restaurant', 'bar', 'night_club',
        'church', 'mosque', 'hindu_temple', 'synagogue',
        'zoo', 'amusement_park', 'lodging',
    ];

    /**
     * Generic nearby search (kept for backward compatibility).
     *
     * @param float $lat
     * @param float $lon
     * @param int   $radius
     * @return array<int, array<string, mixed>>
     */
    public function fetchNearby(float $lat, float $lon, int $radius = 3000): array
    {
        return $this->fetchNearbyByTypes($lat, $lon, $radius, $this->defaultTypes);
    }

    /**
     * Fetch nearby places tailored for a specific trip.
     *
     * - Uses trip start location as the center.
     * - Narrows down Google place types based on trip place categories.
     * - Applies basic quality filters (rating, reviews).
     * - Returns a limited list of recommended places for this trip context.
     *
     * This method returns raw external data; it does NOT attach places to the trip.
     *
     * @param Trip $trip
     * @param int  $radius  Search radius in meters.
     * @param int  $limit   Max number of places to return.
     * @return array<int, array<string, mixed>>
     */
    public function fetchNearbyForTrip(Trip $trip, int $radius = 1500, int $limit = 20): array
    {
        if (!$trip->start_latitude || !$trip->start_longitude) {
            // No start location – we cannot search meaningfully.
            return [];
        }

        $lat = (float) $trip->start_latitude;
        $lon = (float) $trip->start_longitude;

        // Collect distinct category slugs used in this trip (e.g. museum, food, nature, nightlife).
        $categorySlugs = $trip->places()
            ->select('places.category_slug')
            ->distinct()
            ->pluck('category_slug')
            ->all();

        // Map trip categories to Google place types.
        $types = $this->mapCategoriesToGoogleTypes($categorySlugs);

        // Fallback to defaults if we could not derive anything.
        if (empty($types)) {
            $types = $this->defaultTypes;
        }

        $raw = $this->fetchNearbyByTypes($lat, $lon, $radius, $types);

        // Filter and rank results for this trip:
        // - rating >= 4.2
        // - non-zero or decent number of reviews
        // - sort by rating desc, then user_ratings_total desc
        return collect($raw)
            ->filter(function (array $place): bool {
                $rating = $place['rating'] ?? 0.0;
                $reviews = $place['meta']['user_ratings_total'] ?? 0;

                return $rating >= 4.2 && $reviews >= 5;
            })
            ->sortByDesc(function (array $place): array {
                return [
                    $place['rating'] ?? 0.0,
                    $place['meta']['user_ratings_total'] ?? 0,
                ];
            })
            ->take($limit)
            ->values()
            ->all();
    }

    /**
     * Fetch nearby places for a trip based on preferred canonical categories
     * (food/nightlife/museum/nature/attraction).
     *
     * This method is designed for AI suggestions: we derive Google types from user preferences,
     * not from already attached trip places.
     *
     * @param Trip $trip
     * @param array<int,string> $preferredCategorySlugs
     * @param int $radius
     * @param int $limit
     * @return array<int, array<string, mixed>>
     */
    public function fetchNearbyForTripByPreferredCategories(
        Trip $trip,
        array $preferredCategorySlugs,
        int $radius = 1500,
        int $limit = 20
    ): array {
        if (!$trip->start_latitude || !$trip->start_longitude) {
            return [];
        }

        $lat = (float) $trip->start_latitude;
        $lon = (float) $trip->start_longitude;

        $types = $this->mapCategoriesToGoogleTypes($preferredCategorySlugs);

        if (empty($types)) {
            $types = $this->defaultTypes;
        }

        $raw = $this->fetchNearbyByTypes($lat, $lon, $radius, $types);

        return collect($raw)
            ->take($limit)
            ->values()
            ->all();
    }

    /**
     * Low-level nearby search by a given set of Google place types.
     *
     * @param float        $lat
     * @param float        $lon
     * @param int          $radius
     * @param array<int,string> $types
     * @return array<int, array<string, mixed>>
     */
    protected function fetchNearbyByTypes(float $lat, float $lon, int $radius, array $types): array
    {
        $cacheKey = sprintf(
            'google:places:%.4f:%.4f:%d:%s',
            $lat,
            $lon,
            $radius,
            md5(implode(',', $types))
        );

        return Cache::remember($cacheKey, now()->addHours(12), function () use ($lat, $lon, $radius, $types) {
            $results = [];

            foreach ($types as $type) {
                try {
                    $response = Http::timeout(8)->get($this->nearbyUrl, [
                        'location' => "{$lat},{$lon}",
                        'radius'   => $radius,
                        'type'     => $type,
                        'language' => 'pl',
                        'key'      => config('services.google.maps_key'),
                    ]);

                    if (!$response->ok()) {
                        Log::warning("[GooglePlaces] HTTP {$response->status()} for type={$type}");
                        continue;
                    }

                    $data = $response->json();

                    if (($data['status'] ?? 'ZERO_RESULTS') !== 'OK' || empty($data['results'])) {
                        $status = $data['status'] ?? 'unknown';
                        Log::info("[GooglePlaces] Skipped type {$type}: {$status}");
                        continue;
                    }

                    foreach ($data['results'] as $raw) {
                        if (empty($raw['place_id']) || empty($raw['geometry']['location'])) {
                            continue;
                        }

                        // Skip irrelevant business/service types
                        if (collect($raw['types'])->contains(function ($t) {
                            return in_array($t, [
                                'accounting', 'bank', 'pharmacy', 'hospital', 'car_repair',
                                'car_wash', 'insurance_agency', 'lawyer', 'real_estate_agency',
                                'hardware_store', 'supermarket', 'store', 'gas_station',
                            ], true);
                        })) {
                            continue;
                        }

                        // Extra details (opening_hours, website, phone, etc.)
                        $details      = $this->fetchPlaceDetails($raw['place_id']);
                        $openingHours = $details['opening_hours'] ?? null;

                        $results[] = [
                            'place_id'      => $raw['place_id'],
                            'name'          => $raw['name'] ?? 'Unknown place',
                            'lat'           => data_get($raw, 'geometry.location.lat'),
                            'lon'           => data_get($raw, 'geometry.location.lng'),
                            'rating'        => $raw['rating'] ?? null,

                            // NOTE: this is a Google type (not canonical). The AI layer will normalize types.
                            'category_slug' => $type,

                            'opening_hours' => $openingHours,
                            'meta'          => [
                                'address'            => $raw['vicinity'] ?? null,
                                'types'              => $raw['types'] ?? [],
                                'user_ratings_total' => $raw['user_ratings_total'] ?? 0,
                                'business_status'    => $raw['business_status'] ?? null,
                                'icon'               => $raw['icon'] ?? null,
                                'website'            => $details['website'] ?? null,
                                'phone'              => $details['international_phone_number'] ?? null,
                            ],
                        ];
                    }

                    // Small delay to reduce chance of hitting rate limits.
                    usleep(200_000);
                } catch (Throwable $e) {
                    Log::error("[GooglePlaces] Error fetching type={$type}: " . $e->getMessage());
                    continue;
                }
            }

            return collect($results)
                ->unique('place_id')
                ->values()
                ->all();
        });
    }

    /**
     * Fetch extra details for a place — opening hours, website, phone, etc.
     *
     * @param string $placeId
     * @return array<string, mixed>
     */
    protected function fetchPlaceDetails(string $placeId): array
    {
        $cacheKey = "google:place_details:{$placeId}";

        return Cache::remember($cacheKey, now()->addHours(24), function () use ($placeId) {
            try {
                $response = Http::timeout(8)->get($this->detailsUrl, [
                    'place_id' => $placeId,
                    'language' => 'pl',
                    'fields'   => 'opening_hours,website,international_phone_number',
                    'key'      => config('services.google.maps_key'),
                ]);

                if (!$response->ok()) {
                    Log::warning("[GooglePlaces] Details HTTP {$response->status()} pid={$placeId}");
                    return [];
                }

                $data = $response->json();

                if (($data['status'] ?? '') !== 'OK') {
                    Log::info("[GooglePlaces] Details skipped pid={$placeId}: " . ($data['status'] ?? 'unknown'));
                    return [];
                }

                return $data['result'] ?? [];
            } catch (Throwable $e) {
                Log::error("[GooglePlaces] Error fetching details pid={$placeId}: {$e->getMessage()}");
                return [];
            }
        });
    }

    /**
     * Map internal canonical category slugs (museum/food/nature/nightlife/attraction/etc.)
     * to Google place types used in Nearby Search API.
     *
     * @param array<int, string> $categorySlugs
     * @return array<int, string>
     */
    protected function mapCategoriesToGoogleTypes(array $categorySlugs): array
    {
        if (empty($categorySlugs)) {
            return [];
        }

        $map = [
            'museum'    => ['museum', 'art_gallery'],
            'nature'    => ['park', 'zoo', 'campground', 'tourist_attraction'],
            'food'      => ['restaurant', 'cafe', 'bakery', 'bar'],
            'nightlife' => ['bar', 'night_club', 'pub'],

            // Canonical attraction -> general POIs / attractions
            'attraction' => ['tourist_attraction', 'point_of_interest', 'tourist_information_center'],

            // Technical (kept for compatibility; AI layer can exclude them)
            'hotel'     => ['lodging'],
            'airport'   => ['airport'],
            'station'   => ['train_station', 'subway_station', 'bus_station', 'transit_station'],

            // Optional legacy aliases
            'religion'        => ['church', 'mosque', 'synagogue'],
            'accommodation'   => ['lodging'],
        ];

        $types = [];
        foreach ($categorySlugs as $slug) {
            $slug = strtolower((string) $slug);
            if (isset($map[$slug])) {
                $types = array_merge($types, $map[$slug]);
            }
        }

        return array_values(array_unique($types));
    }
}
