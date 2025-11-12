<?php

namespace App\Services\External;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class GooglePlacesService
{
    protected string $baseUrl = 'https://maps.googleapis.com/maps/api/place/nearbysearch/json';

    /**
     * Fetch nearby interesting places (filtered + cached).
     *
     * @param float $lat
     * @param float $lon
     * @param int $radius
     * @return array<int, array<string, mixed>>
     */
    public function fetchNearby(float $lat, float $lon, int $radius = 3000): array
    {
        $allowedTypes = [
            'tourist_attraction', 'museum', 'art_gallery', 'park',
            'cafe', 'restaurant', 'bar', 'night_club',
            'church', 'mosque', 'hindu_temple', 'synagogue',
            'zoo', 'amusement_park', 'lodging',
        ];

        $cacheKey = sprintf('google:places:%.4f:%.4f:%d', $lat, $lon, $radius);

        return Cache::remember($cacheKey, now()->addHours(12), function () use ($lat, $lon, $radius, $allowedTypes) {
            $results = [];

            foreach ($allowedTypes as $type) {
                try {
                    $response = Http::timeout(8)->get($this->baseUrl, [
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
                        Log::info("[GooglePlaces] Skipped {$type}: {$status}");
                        continue;
                    }

                    foreach ($data['results'] as $raw) {
                        if (empty($raw['place_id']) || empty($raw['geometry']['location'])) {
                            continue;
                        }

                        // Skip irrelevant business/service types
                        if (collect($raw['types'])->contains(fn($t) => in_array($t, [
                            'accounting', 'bank', 'pharmacy', 'hospital', 'car_repair',
                            'car_wash', 'insurance_agency', 'lawyer', 'real_estate_agency',
                            'hardware_store', 'supermarket', 'store', 'gas_station',
                        ]))) {
                            continue;
                        }

                        $results[] = [
                            'place_id'      => $raw['place_id'],
                            'name'          => $raw['name'] ?? 'Unknown place',
                            'lat'           => data_get($raw, 'geometry.location.lat'),
                            'lon'           => data_get($raw, 'geometry.location.lng'),
                            'rating'        => $raw['rating'] ?? null,
                            'category_slug' => $type,
                            'meta'          => [
                                'address'           => $raw['vicinity'] ?? null,
                                'types'             => $raw['types'] ?? [],
                                'user_ratings_total'=> $raw['user_ratings_total'] ?? 0,
                                'business_status'   => $raw['business_status'] ?? null,
                                'icon'              => $raw['icon'] ?? null,
                            ],
                        ];
                    }

                    usleep(200000);
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
}
