<?php

namespace App\Services\External;

use App\Models\Trip;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class GooglePlacesService
{
    protected string $nearbyUrlV1     = 'https://places.googleapis.com/v1/places:searchNearby';
    protected string $detailsUrl      = 'https://maps.googleapis.com/maps/api/place/details/json';
    protected string $autocompleteUrl = 'https://maps.googleapis.com/maps/api/place/autocomplete/json';

    protected array $defaultTypes = [
        'tourist_attraction', 'museum', 'art_gallery', 'park',
        'cafe', 'restaurant', 'bar', 'night_club',
        'zoo', 'amusement_park', 'lodging',
        'church', 'mosque', 'hindu_temple', 'synagogue',
        'campground',
        'airport', 'train_station', 'subway_station', 'bus_station', 'transit_station',
        'tourist_information_center',
        'bakery',
    ];

    protected array $unsupportedIncludedTypesV1 = [
        'point_of_interest',
        'pub',
    ];

    protected array $typeMapping = [
        'restaurant' => 'food',
        'cafe' => 'food',
        'meal_takeaway' => 'food',
        'meal_delivery' => 'food',
        'bakery' => 'food',
        'bar' => 'food',
        'food' => 'food',

        'night_club' => 'nightlife',
        'pub' => 'nightlife',

        'museum' => 'museum',
        'art_gallery' => 'museum',
        'library' => 'museum',

        'park' => 'nature',
        'natural_feature' => 'nature',
        'campground' => 'nature',
        'tourist_attraction' => 'nature',

        'point_of_interest' => 'attraction',
        'tourist_information_center' => 'attraction',

        'lodging' => 'hotel',
        'hotel' => 'hotel',
        'hostel' => 'hotel',
        'motel' => 'hotel',
        'guest_house' => 'hotel',
        'apartment' => 'hotel',

        'airport' => 'airport',
        'train_station' => 'station',
        'subway_station' => 'station',
        'bus_station' => 'station',
        'transit_station' => 'station',

        'church' => 'religion',
        'mosque' => 'religion',
        'synagogue' => 'religion',
        'hindu_temple' => 'religion',
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

        try {
            $params = [
                'input'    => $query,
                'language' => $language,
                'key'      => config('services.google.maps_server_key'),
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

            $res = Http::timeout(5)->get($this->autocompleteUrl, $params);

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

            return collect($predictions)
                ->map(static function (array $p): array {
                    return [
                        'google_place_id' => $p['place_id'] ?? null,
                        'description'     => $p['description'] ?? null,
                        'main_text'       => data_get($p, 'structured_formatting.main_text'),
                        'secondary_text'  => data_get($p, 'structured_formatting.secondary_text'),
                        'types'           => $p['types'] ?? [],
                    ];
                })
                ->filter(static fn ($x) => !empty($x['google_place_id']))
                ->values()
                ->all();
        } catch (Throwable $e) {
            Log::error("[GooglePlaces] Autocomplete error: {$e->getMessage()}");
            return [];
        }
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
                    'key'      => config('services.google.maps_server_key'),
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

                $googleTypes = $r['types'] ?? [];
                $internalCategory = $this->resolveInternalCategory($googleTypes);

                return [
                    'google_place_id' => $r['place_id'] ?? $googlePlaceId,
                    'place_id'        => $r['place_id'] ?? $googlePlaceId,
                    'name'            => $r['name'] ?? 'Unknown place',
                    'lat'             => (float) $lat,
                    'lon'             => (float) $lon,
                    'category_slug'   => $internalCategory,
                    'types'           => $googleTypes,
                    'rating'          => isset($r['rating']) ? (float) $r['rating'] : null,
                    'opening_hours'   => $r['opening_hours'] ?? null,
                    'meta'            => [
                        'address'            => $r['formatted_address'] ?? ($r['vicinity'] ?? null),
                        'user_ratings_total' => $r['user_ratings_total'] ?? 0,
                        'website'            => $r['website'] ?? null,
                        'phone'              => $r['international_phone_number'] ?? null,
                        'types'              => $googleTypes,
                    ],
                ];
            } catch (Throwable $e) {
                Log::error("[GooglePlaces] Details error pid={$googlePlaceId}: {$e->getMessage()}");
                return null;
            }
        });
    }

    public function fetchNearby(float $lat, float $lon, int $radius = 3000, string $language = 'pl'): array
    {
        return $this->fetchNearbyByTypes($lat, $lon, $radius, $this->defaultTypes, $language);
    }

    public function fetchNearbyForTrip(Trip $trip, int $radius = 1500, int $limit = 20, string $language = 'pl'): array
    {
        $coords = $this->resolveTripCoords($trip);
        if (!$coords) {
            return [];
        }

        [$lat, $lon] = $coords;

        $categorySlugs = $trip->places()
            ->select('places.category_slug')
            ->distinct()
            ->pluck('category_slug')
            ->all();

        $types = $this->mapCategoriesToGoogleTypes($categorySlugs);

        $raw = $this->fetchNearbyByTypes($lat, $lon, $radius, $types, $language);

        $minRating = (float) config('ai.suggestions.quality.min_rating', 0);
        $minReviews = (int) config('ai.suggestions.quality.min_reviews', 0);

        return collect($raw)
            ->filter(static function (array $place) use ($minRating, $minReviews): bool {
                $rating = (float) ($place['rating'] ?? 0.0);
                $reviews = (int) data_get($place, 'meta.user_ratings_total', 0);
                return $rating >= $minRating && $reviews >= $minReviews;
            })
            ->sortByDesc('meta.user_ratings_total')
            ->sortByDesc('rating')
            ->take($limit)
            ->values()
            ->all();
    }

    public function fetchNearbyForTripByPreferredCategories(
        Trip $trip,
        array $preferredCategorySlugs,
        int $radius = 1500,
        int $limit = 20,
        string $language = 'pl'
    ): array {
        $coords = $this->resolveTripCoords($trip);
        if (!$coords) {
            return [];
        }

        [$lat, $lon] = $coords;

        $types = $this->mapCategoriesToGoogleTypes($preferredCategorySlugs);
        $raw = $this->fetchNearbyByTypes($lat, $lon, $radius, $types, $language);

        return collect($raw)->take($limit)->values()->all();
    }

    public function fetchNearbyByPointAndPreferredCategories(
        float $lat,
        float $lon,
        array $preferredCategorySlugs,
        int $radius = 1500,
        int $limit = 20,
        string $language = 'pl'
    ): array {
        $types = $this->mapCategoriesToGoogleTypes($preferredCategorySlugs);
        $raw = $this->fetchNearbyByTypes($lat, $lon, $radius, $types, $language);

        return collect($raw)->take($limit)->values()->all();
    }

    protected function fetchNearbyByTypes(float $lat, float $lon, int $radius, array $types, string $language = 'pl'): array
    {
        $types = $this->sanitizeIncludedTypes($types);
        sort($types);

        $typesKey = empty($types) ? 'ALL' : md5(implode(',', $types));

        $cacheKey = sprintf(
            'google:places:v1:%.4f:%.4f:%d:%s:%s',
            $lat,
            $lon,
            $radius,
            $language,
            $typesKey
        );

        $cached = Cache::get($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }

        $result = $this->doNearbyRequest($lat, $lon, $radius, $types, $language);

        if ($result['ok'] === false) {
            return [];
        }

        Cache::put($cacheKey, $result['places'], now()->addHours(6));
        return $result['places'];
    }

    private function doNearbyRequest(float $lat, float $lon, int $radius, array $types, string $language): array
    {
        try {
            $body = [
                'maxResultCount' => 20,
                'locationRestriction' => [
                    'circle' => [
                        'center' => [
                            'latitude'  => (float) $lat,
                            'longitude' => (float) $lon,
                        ],
                        'radius' => (float) $radius,
                    ],
                ],
                'languageCode' => $language,
            ];

            if (!empty($types)) {
                $body['includedTypes'] = $types;
            }

            $response = Http::timeout(10)
                ->withHeaders([
                    'X-Goog-Api-Key'   => config('services.google.maps_server_key'),
                    'X-Goog-FieldMask' => 'places.id,places.displayName,places.location,places.types,places.rating,places.userRatingCount,places.formattedAddress',
                    'Referer'          => (string) config('app.url'),
                ])
                ->post($this->nearbyUrlV1, $body);

            if (!$response->successful()) {
                Log::warning("[GooglePlaces V1] Error {$response->status()} types=[" . implode(',', $types) . "]: " . $response->body());
                return ['ok' => false, 'places' => []];
            }

            $data = $response->json();
            $places = $data['places'] ?? [];

            $mapped = collect($places)
                ->map(function ($place) {
                    $googleTypes = $place['types'] ?? [];
                    $internalCategory = $this->resolveInternalCategory($googleTypes);

                    return [
                        'place_id'        => $place['id'] ?? null,
                        'google_place_id' => $place['id'] ?? null,
                        'name'            => data_get($place, 'displayName.text', 'Unknown'),
                        'lat'             => (float) data_get($place, 'location.latitude', 0),
                        'lon'             => (float) data_get($place, 'location.longitude', 0),
                        'rating'          => isset($place['rating']) ? (float) $place['rating'] : null,
                        'category_slug'   => $internalCategory,
                        'opening_hours'   => null,
                        'meta'            => [
                            'address'            => $place['formattedAddress'] ?? null,
                            'types'              => $googleTypes,
                            'user_ratings_total' => $place['userRatingCount'] ?? 0,
                            'business_status'    => null,
                            'icon'               => null,
                        ],
                    ];
                })
                ->filter(static fn ($p) => !empty($p['place_id']))
                ->values()
                ->all();

            return ['ok' => true, 'places' => $mapped];
        } catch (Throwable $e) {
            Log::error("[GooglePlaces V1] Exception: {$e->getMessage()}");
            return ['ok' => false, 'places' => []];
        }
    }

    private function sanitizeIncludedTypes(array $types): array
    {
        $types = array_map(static fn ($t) => strtolower(trim((string) $t)), $types);
        $types = array_values(array_filter($types, static fn ($t) => $t !== ''));
        $types = array_values(array_diff($types, $this->unsupportedIncludedTypesV1));
        $types = array_values(array_unique($types));

        if (empty($types)) {
            $types = array_values(array_diff($this->defaultTypes, $this->unsupportedIncludedTypesV1));
        }

        return $types;
    }

    protected function mapCategoriesToGoogleTypes(array $categorySlugs): array
    {
        if (empty($categorySlugs)) {
            return [];
        }

        $map = [
            'museum'         => ['museum', 'art_gallery'],
            'nature'         => ['park', 'zoo', 'campground', 'tourist_attraction'],
            'food'           => ['restaurant', 'cafe', 'bakery', 'bar'],
            'nightlife'      => ['bar', 'night_club'],
            'attraction'     => ['tourist_attraction', 'tourist_information_center'],
            'hotel'          => ['lodging'],
            'airport'        => ['airport'],
            'station'        => ['train_station', 'subway_station', 'bus_station', 'transit_station'],
            'religion'       => ['church', 'mosque', 'synagogue', 'hindu_temple'],
            'accommodation'  => ['lodging'],
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

    private function resolveInternalCategory(array $googleTypes): string
    {
        foreach ($googleTypes as $gType) {
            if (isset($this->typeMapping[$gType])) {
                return $this->typeMapping[$gType];
            }
        }

        return 'other';
    }

    private function resolveTripCoords(Trip $trip): ?array
    {
        if ($trip->start_latitude !== null && $trip->start_longitude !== null) {
            return [(float) $trip->start_latitude, (float) $trip->start_longitude];
        }

        if (!empty($trip->start_location)) {
            $row = DB::table('trips')
                ->where('id', $trip->id)
                ->selectRaw('ST_Y(start_location::geometry) as lat, ST_X(start_location::geometry) as lon')
                ->first();

            if ($row && $row->lat !== null && $row->lon !== null) {
                return [(float) $row->lat, (float) $row->lon];
            }
        }

        return null;
    }
}
