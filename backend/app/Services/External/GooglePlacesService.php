<?php

namespace App\Services\External;

use App\Models\Trip;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class GooglePlacesService
{
    protected string $nearbyUrl       = 'https://maps.googleapis.com/maps/api/place/nearbysearch/json';
    protected string $detailsUrl      = 'https://maps.googleapis.com/maps/api/place/details/json';
    protected string $autocompleteUrl = 'https://maps.googleapis.com/maps/api/place/autocomplete/json';

    protected array $defaultTypes = [
        'tourist_attraction', 'museum', 'art_gallery', 'park',
        'cafe', 'restaurant', 'bar', 'night_club',
        'church', 'mosque', 'hindu_temple', 'synagogue',
        'zoo', 'amusement_park', 'lodging',
    ];

    public function autocomplete(
        string $query,
        ?float $lat = null,
        ?float $lon = null,
        ?int $radius = null,
        string $language = 'pl',
        ?string $sessionToken = null
    ): array {
        $query = trim($query);
        if ($query === '') {
            return [];
        }

        $cacheKey = sprintf(
            'google:autocomplete:%s:%s:%s:%s:%s',
            md5(mb_strtolower($query)),
            $lat !== null ? number_format($lat, 4) : 'n',
            $lon !== null ? number_format($lon, 4) : 'n',
            $radius !== null ? (string) $radius : 'n',
            $language
        );

        return Cache::remember($cacheKey, now()->addMinutes(5), function () use ($query, $lat, $lon, $radius, $language, $sessionToken) {
            try {
                $params = [
                    'input'    => $query,
                    'language' => $language,
                    'key'      => config('services.google.maps_key'),
                ];

                if ($lat !== null && $lon !== null) {
                    $params['location'] = "{$lat},{$lon}";
                    if ($radius !== null) {
                        $params['radius'] = $radius;
                    }
                }

                if ($sessionToken) {
                    $params['sessiontoken'] = $sessionToken;
                }

                $res = Http::timeout(8)->get($this->autocompleteUrl, $params);

                if (!$res->ok()) {
                    Log::warning("[GooglePlaces] Autocomplete HTTP {$res->status()}");
                    return [];
                }

                $data = $res->json();
                $status = $data['status'] ?? 'unknown';

                if ($status !== 'OK') {
                    if ($status !== 'ZERO_RESULTS') {
                        Log::info("[GooglePlaces] Autocomplete status={$status}");
                    }
                    return [];
                }

                $predictions = $data['predictions'] ?? [];

                return collect($predictions)->map(function (array $p) {
                    return [
                        'google_place_id' => $p['place_id'] ?? null,
                        'description'     => $p['description'] ?? null,
                        'main_text'       => data_get($p, 'structured_formatting.main_text'),
                        'secondary_text'  => data_get($p, 'structured_formatting.secondary_text'),
                        'types'           => $p['types'] ?? [],
                    ];
                })->filter(fn ($x) => !empty($x['google_place_id']))->values()->all();

            } catch (Throwable $e) {
                Log::error("[GooglePlaces] Autocomplete error: {$e->getMessage()}");
                return [];
            }
        });
    }

    public function getPlaceDetails(string $googlePlaceId, string $language = 'pl', ?string $sessionToken = null): ?array
    {
        $googlePlaceId = trim($googlePlaceId);
        if ($googlePlaceId === '') {
            return null;
        }

        $cacheKey = "google:place_details_full:{$googlePlaceId}:{$language}";

        return Cache::remember($cacheKey, now()->addHours(24), function () use ($googlePlaceId, $language, $sessionToken) {
            try {
                $params = [
                    'place_id' => $googlePlaceId,
                    'language' => $language,
                    'fields'   => 'place_id,name,geometry,types,rating,user_ratings_total,vicinity,formatted_address,opening_hours,website,international_phone_number',
                    'key'      => config('services.google.maps_key'),
                ];

                if ($sessionToken) {
                    $params['sessiontoken'] = $sessionToken;
                }

                $res = Http::timeout(8)->get($this->detailsUrl, $params);

                if (!$res->ok()) {
                    Log::warning("[GooglePlaces] Details HTTP {$res->status()} pid={$googlePlaceId}");
                    return null;
                }

                $data = $res->json();
                if (($data['status'] ?? '') !== 'OK') {
                    Log::info("[GooglePlaces] Details status=" . ($data['status'] ?? 'unknown') . " pid={$googlePlaceId}");
                    return null;
                }

                $r = $data['result'] ?? null;
                if (!$r) {
                    return null;
                }

                $lat = data_get($r, 'geometry.location.lat');
                $lon = data_get($r, 'geometry.location.lng');
                if ($lat === null || $lon === null) {
                    return null;
                }

                return [
                    'google_place_id' => $r['place_id'] ?? $googlePlaceId,
                    'name'            => $r['name'] ?? 'Unknown place',
                    'lat'             => (float) $lat,
                    'lon'             => (float) $lon,
                    'types'           => $r['types'] ?? [],
                    'rating'          => isset($r['rating']) ? (float) $r['rating'] : null,
                    'opening_hours'   => $r['opening_hours'] ?? null,
                    'meta'            => [
                        'address'            => $r['formatted_address'] ?? ($r['vicinity'] ?? null),
                        'user_ratings_total' => $r['user_ratings_total'] ?? 0,
                        'website'            => $r['website'] ?? null,
                        'phone'              => $r['international_phone_number'] ?? null,
                        'types'              => $r['types'] ?? [],
                    ],
                ];
            } catch (Throwable $e) {
                Log::error("[GooglePlaces] Details error pid={$googlePlaceId}: {$e->getMessage()}");
                return null;
            }
        });
    }

    public function fetchNearby(float $lat, float $lon, int $radius = 3000): array
    {
        return $this->fetchNearbyByTypes($lat, $lon, $radius, $this->defaultTypes);
    }

    public function fetchNearbyForTrip(Trip $trip, int $radius = 1500, int $limit = 20): array
    {
        if (!$trip->start_latitude || !$trip->start_longitude) {
            return [];
        }

        $lat = (float) $trip->start_latitude;
        $lon = (float) $trip->start_longitude;

        $categorySlugs = $trip->places()
            ->select('places.category_slug')
            ->distinct()
            ->pluck('category_slug')
            ->all();

        $types = $this->mapCategoriesToGoogleTypes($categorySlugs);
        if (empty($types)) {
            $types = $this->defaultTypes;
        }

        $raw = $this->fetchNearbyByTypes($lat, $lon, $radius, $types);

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

        return collect($raw)->take($limit)->values()->all();
    }

    protected function fetchNearbyByTypes(float $lat, float $lon, int $radius, array $types): array
    {
        $cacheKey = sprintf(
            'google:places:%.4f:%.4f:%d:%s',
            $lat,
            $lon,
            $radius,
            md5(implode(',', $types))
        );

        return Cache::remember($cacheKey, now()->addHours(6), function () use ($lat, $lon, $radius, $types) {
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
                        Log::warning("[GooglePlaces] Nearby HTTP {$response->status()} type={$type}");
                        continue;
                    }

                    $data = $response->json();
                    if (($data['status'] ?? 'ZERO_RESULTS') !== 'OK' || empty($data['results'])) {
                        continue;
                    }

                    foreach ($data['results'] as $raw) {
                        if (empty($raw['place_id']) || empty($raw['geometry']['location'])) {
                            continue;
                        }

                        $results[] = [
                            'place_id'      => $raw['place_id'],
                            'name'          => $raw['name'] ?? 'Unknown place',
                            'lat'           => data_get($raw, 'geometry.location.lat'),
                            'lon'           => data_get($raw, 'geometry.location.lng'),
                            'rating'        => $raw['rating'] ?? null,
                            'category_slug' => $type,
                            'opening_hours' => null,
                            'meta'          => [
                                'address'            => $raw['vicinity'] ?? null,
                                'types'              => $raw['types'] ?? [],
                                'user_ratings_total' => $raw['user_ratings_total'] ?? 0,
                                'business_status'    => $raw['business_status'] ?? null,
                                'icon'               => $raw['icon'] ?? null,
                            ],
                        ];
                    }

                    usleep(150_000);
                } catch (Throwable $e) {
                    Log::error("[GooglePlaces] Nearby error type={$type}: {$e->getMessage()}");
                    continue;
                }
            }

            return collect($results)->unique('place_id')->values()->all();
        });
    }

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
            'attraction' => ['tourist_attraction', 'point_of_interest', 'tourist_information_center'],
            'hotel'     => ['lodging'],
            'airport'   => ['airport'],
            'station'   => ['train_station', 'subway_station', 'bus_station', 'transit_station'],
            'religion'  => ['church', 'mosque', 'synagogue'],
            'accommodation' => ['lodging'],
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
