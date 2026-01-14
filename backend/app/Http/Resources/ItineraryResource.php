<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

/**
 * @OA\Schema(
 * schema="ItineraryResource",
 * title="Itinerary Resource",
 * description="Generated itinerary with daily schedule and caching details"
 * )
 */
class ItineraryResource extends JsonResource
{
    public function toArray($request): array
    {
        $schedule = $this->resource->schedule ?? [];
        $cache = (array) ($this->resource->cache_info ?? []);

        return [
            'trip_id'   => (int) ($this->resource->trip_id ?? 0),
            'day_count' => (int) ($this->resource->day_count ?? 0),

            'schedule' => collect($schedule)->map(function ($dayItem) {
                $day = (int) ($dayItem->day ?? ($dayItem['day'] ?? 0));
                $places = $dayItem->places ?? ($dayItem['places'] ?? []);

                return [
                    'day' => $day,
                    'places' => collect($places)->map(function ($p) {
                        return [
                            'id'            => (int) ($p->id ?? ($p['id'] ?? 0)),
                            'name'          => (string) ($p->name ?? ($p['name'] ?? '')),
                            'category_slug' => (string) ($p->category_slug ?? ($p['category_slug'] ?? 'other')),
                            'score'         => (float) ($p->score ?? ($p['score'] ?? 0.0)),
                            'distance_m'    => (int) ($p->distance_m ?? ($p['distance_m'] ?? 0)),
                        ];
                    })->values()->all(),
                ];
            })->values()->all(),

            'cache_info' => [
                'cached'     => (bool) ($cache['cached'] ?? false),
                'cached_at'  => $cache['cached_at'] ?? null,
                'expires_in' => $cache['expires_in'] ?? null,
                'source'     => $cache['source'] ?? null,

                'mode'      => $cache['mode'] ?? null,
                'algorithm' => $cache['algorithm'] ?? null,
                'origin'    => $cache['origin'] ?? null,
            ],
        ];
    }
}
