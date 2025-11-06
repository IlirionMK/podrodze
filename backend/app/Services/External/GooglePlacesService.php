<?php

namespace App\Services\External;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class GooglePlacesService
{
    protected string $baseUrl = 'https://maps.googleapis.com/maps/api/place/nearbysearch/json';

    /**
     * Fetch nearby interesting places (filtered + cached)
     */
    public function fetchNearby(float $lat, float $lon, int $radius = 3000): array
    {
        $allowedTypes = [
            'tourist_attraction', 'museum', 'art_gallery', 'park',
            'cafe', 'restaurant', 'bar', 'night_club',
            'church', 'mosque', 'hindu_temple', 'synagogue',
            'zoo', 'amusement_park', 'lodging'
        ];

        $cacheKey = "google:places:{$lat}:{$lon}:{$radius}";
        return Cache::remember($cacheKey, now()->addHours(12), function () use ($lat, $lon, $radius, $allowedTypes) {
            $results = [];

            foreach ($allowedTypes as $type) {
                $response = Http::get($this->baseUrl, [
                    'location' => "$lat,$lon",
                    'radius'   => $radius,
                    'type'     => $type,
                    'key'      => config('services.google.maps_key'),
                    'language' => 'pl',
                ]);

                if (!$response->ok()) {
                    Log::warning("[GooglePlaces] HTTP {$response->status()} for type={$type}");
                    continue;
                }

                $data = $response->json();
                if (($data['status'] ?? 'ZERO_RESULTS') !== 'OK') {
                    Log::info("[GooglePlaces] Skipped {$type}: {$data['status']}");
                    continue;
                }

                foreach ($data['results'] as $raw) {
                    if (empty($raw['place_id']) || empty($raw['geometry']['location'])) {
                        continue;
                    }

                    if (collect($raw['types'])->contains(fn($t) => in_array($t, [
                        'accounting', 'bank', 'pharmacy', 'hospital', 'car_repair',
                        'car_wash', 'insurance_agency', 'lawyer', 'real_estate_agency',
                        'hardware_store', 'supermarket', 'store', 'gas_station',
                    ]))) continue;

                    $results[] = [
                        'place_id' => $raw['place_id'],
                        'name' => $raw['name'] ?? 'Unknown place',
                        'lat' => $raw['geometry']['location']['lat'] ?? null,
                        'lon' => $raw['geometry']['location']['lng'] ?? null,
                        'rating' => $raw['rating'] ?? null,
                        'category_slug' => $type,
                        'meta' => [
                            'address' => $raw['vicinity'] ?? null,
                            'types' => $raw['types'] ?? [],
                            'user_ratings_total' => $raw['user_ratings_total'] ?? 0,
                            'business_status' => $raw['business_status'] ?? null,
                            'icon' => $raw['icon'] ?? null,
                        ],
                    ];
                }

                usleep(200000);
            }

            return collect($results)
                ->unique('place_id')
                ->values()
                ->all();
        });
    }
}
