<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ItineraryResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'trip_id'   => $this->resource->trip_id,
            'day_count' => $this->resource->day_count,

            'schedule' => collect($this->resource->schedule)->map(function ($dayItem) {
                return [
                    'day'    => $dayItem['day'],
                    'places' => collect($dayItem['places'])->map(function ($p) {
                        return [
                            'id'           => $p['id'],
                            'name'         => $p['name'],
                            'category_slug'=> $p['category_slug'],
                            'score'        => $p['score'],
                            'distance_m'   => $p['distance_m'],
                        ];
                    }),
                ];
            }),

            'cache_info' => [
                'cached'      => (bool) $this->resource->cache_info['cached'],
                'cached_at'   => $this->resource->cache_info['cached_at'] ?? null,
                'expires_in'  => $this->resource->cache_info['expires_in'] ?? null,
                'source'      => $this->resource->cache_info['source'] ?? null,
            ],
        ];
    }
}
