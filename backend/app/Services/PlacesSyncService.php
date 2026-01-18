<?php

namespace App\Services;

use App\Interfaces\PlacesSyncInterface;
use App\Models\Place;
use App\Services\External\GooglePlacesService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PlacesSyncService implements PlacesSyncInterface
{
    public function __construct(
        protected GooglePlacesService $googlePlaces
    ) {}

    public function fetchAndStore(float $lat, float $lon, int $radius = 3000): array
    {
        $places = $this->googlePlaces->fetchNearby($lat, $lon, $radius);

        $result = DB::transaction(function () use ($places) {
            $added = 0;
            $updated = 0;

            foreach ($places as $item) {
                if (empty($item['place_id']) || empty($item['lat']) || empty($item['lon'])) {
                    continue;
                }

                $placeId = (string) $item['place_id'];

                $meta = $item['meta'] ?? [];
                if (!is_array($meta)) {
                    $meta = [];
                }

                $googleTypes = [];

                if (!empty($item['category_slug'])) {
                    $googleTypes[] = (string) $item['category_slug'];
                }

                $metaTypes = $meta['types'] ?? [];
                if (is_array($metaTypes)) {
                    $googleTypes = array_merge($googleTypes, $metaTypes);
                }

                $googleTypes = array_values(array_unique(array_filter(array_map(
                    fn ($t) => strtolower(trim((string) $t)),
                    $googleTypes
                ))));

                $categorySlug = $this->mapGoogleTypesToCategory($googleTypes);

                $meta = array_merge($meta, [
                    'source' => $meta['source'] ?? 'google',
                    'google_types' => $googleTypes,
                ]);

                $payload = [
                    'name'          => $item['name'] ?? 'Unknown place',
                    'category_slug' => $categorySlug,
                    'rating'        => $item['rating'] ?? null,
                    'meta'          => $meta,
                    'opening_hours' => $item['opening_hours'] ?? null,
                    'location'      => DB::raw(sprintf(
                        "ST_SetSRID(ST_MakePoint(%F, %F), 4326)",
                        (float) $item['lon'],
                        (float) $item['lat']
                    )),
                ];

                $model = Place::updateOrCreate(
                    ['google_place_id' => $placeId],
                    $payload
                );

                if ($model->wasRecentlyCreated) {
                    $added++;
                } else {
                    $updated++;
                }
            }

            return [
                'added' => $added,
                'updated' => $updated,
            ];
        });

        Log::info('[PlacesSyncService] Synced places', $result);

        return $result;
    }

    private function mapGoogleTypesToCategory(array $types): string
    {
        foreach ($types as $type) {
            $mapped = Config::get("google_category_map.$type");
            if ($mapped) {
                return (string) $mapped;
            }
        }

        return 'other';
    }
}
