<?php

namespace App\Services;

use App\Interfaces\PlacesSyncInterface;
use App\Models\Place;
use App\Services\External\GooglePlacesService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class PlacesSyncService implements PlacesSyncInterface
{
    public function __construct(
        protected GooglePlacesService $googlePlaces
    ) {}

    /**
     * Fetch and store nearby interesting places from Google API.
     *
     * @param float $lat
     * @param float $lon
     * @param int $radius
     * @return array{added: int, updated: int}
     */
    public function fetchAndStore(float $lat, float $lon, int $radius = 3000): array
    {
        $places = $this->googlePlaces->fetchNearby($lat, $lon, $radius);

        $added = 0;
        $updated = 0;

        DB::beginTransaction();

        try {
            foreach ($places as $item) {
                if (empty($item['place_id']) || empty($item['lat']) || empty($item['lon'])) {
                    continue;
                }

                $locationWKT = sprintf('SRID=4326;POINT(%F %F)', $item['lon'], $item['lat']);

                $payload = [
                    'name'          => $item['name'],
                    'category_slug' => $item['category_slug'],
                    'rating'        => $item['rating'],
                    'meta'          => json_encode($item['meta'] ?? []),
                    'opening_hours' => json_encode($item['opening_hours'] ?? null),
                    'location'      => DB::raw("ST_GeomFromText('$locationWKT')"),
                    'updated_at'    => now(),
                ];

                $existing = Place::where('google_place_id', $item['place_id'])->first();

                if ($existing) {
                    $existing->update($payload);
                    $updated++;
                } else {
                    $payload['google_place_id'] = $item['place_id'];
                    $payload['created_at'] = now();

                    Place::create($payload);
                    $added++;
                }
            }

            DB::commit();

        } catch (Throwable $e) {
            DB::rollBack();

            Log::error('[PlacesSyncService] Error syncing places', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }

        Log::info("[PlacesSyncService] Synced {$added} added / {$updated} updated places");

        return [
            'added'   => $added,
            'updated' => $updated,
        ];
    }
}
