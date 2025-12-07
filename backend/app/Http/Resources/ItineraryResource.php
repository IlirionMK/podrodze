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
    /**
     * @OA\Property(property="trip_id", type="integer", example=10, description="ID of the associated trip")
     * @OA\Property(property="day_count", type="integer", example=3, description="Total number of days in the itinerary")
     *
     * @OA\Property(
     * property="schedule",
     * type="array",
     * description="List of days with planned places",
     * @OA\Items(
     * type="object",
     * @OA\Property(property="day", type="integer", example=1, description="Day number"),
     * @OA\Property(
     * property="places",
     * type="array",
     * description="List of places for this day",
     * @OA\Items(
     * type="object",
     * @OA\Property(property="id", type="integer", example=123),
     * @OA\Property(property="name", type="string", example="Central Park"),
     * @OA\Property(property="category_slug", type="string", example="park"),
     * @OA\Property(property="score", type="number", format="float", example=9.5, description="Relevance score or rating"),
     * @OA\Property(property="distance_m", type="number", format="float", nullable=true, example=500, description="Distance from previous point")
     * )
     * )
     * )
     * )
     *
     * @OA\Property(
     * property="cache_info",
     * type="object",
     * description="Metadata about the generated route caching",
     * @OA\Property(property="cached", type="boolean", example=true),
     * @OA\Property(property="cached_at", type="string", format="date-time", nullable=true, example="2025-06-01T10:00:00.000000Z"),
     * @OA\Property(property="expires_in", type="integer", nullable=true, example=3600, description="Seconds until expiration"),
     * @OA\Property(property="source", type="string", nullable=true, example="redis", description="Source of the data")
     * )
     */
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
