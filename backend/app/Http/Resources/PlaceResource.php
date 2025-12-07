<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

/**
 * @OA\Schema(
 * schema="PlaceResource",
 * title="Place Resource",
 * description="Detailed information about a specific location or venue"
 * )
 */
class PlaceResource extends JsonResource
{
    /**
     * @OA\Property(property="id", type="integer", example=100, description="Internal Place ID")
     * @OA\Property(property="google_place_id", type="string", example="ChIJN1t_tDeuEmsRUsoyG83frY4", description="Google Places API ID")
     * @OA\Property(property="name", type="string", example="Sydney Opera House", description="Name of the place")
     * @OA\Property(property="category_slug", type="string", example="landmark", description="Category identifier")
     * @OA\Property(property="rating", type="number", format="float", example=4.8, description="Average rating")
     *
     * @OA\Property(
     * property="meta",
     * type="object",
     * nullable=true,
     * description="Additional metadata (e.g., photos, opening hours) as JSON object"
     * )
     *
     * @OA\Property(property="lat", type="number", format="float", nullable=true, example=-33.8568, description="Latitude")
     * @OA\Property(property="lon", type="number", format="float", nullable=true, example=151.2153, description="Longitude")
     *
     * @OA\Property(property="distance_m", type="number", format="float", nullable=true, example=150.5, description="Distance in meters (if applicable)")
     *
     * @OA\Property(property="created_at", type="string", format="date-time", example="2024-01-01T12:00:00.000000Z")
     * @OA\Property(property="updated_at", type="string", format="date-time", example="2024-01-01T12:00:00.000000Z")
     */
    public function toArray($request): array
    {
        return [
            'id'              => $this->id,
            'google_place_id' => $this->google_place_id,
            'name'            => $this->name,
            'category_slug'   => $this->category_slug,
            'rating'          => $this->rating,

            'meta'            => is_array($this->meta) ? $this->meta : json_decode($this->meta, true),

            'lat'             => $this->lat ?? null,
            'lon'             => $this->lon ?? null,

            'distance_m'      => isset($this->distance_m)
                ? round((float) $this->distance_m, 1)
                : null,

            'created_at'      => optional($this->created_at)->toISOString(),
            'updated_at'      => optional($this->updated_at)->toISOString(),
        ];
    }
}
