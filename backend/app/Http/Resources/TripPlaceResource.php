<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use App\DTO\Trip\TripPlace;
use OpenApi\Attributes as OA;

/**
 * @OA\Schema(
 * schema="TripPlaceResource",
 * title="Trip Place Resource",
 * description="Resource representing a place within a trip"
 * )
 * @mixin TripPlace
 */
class TripPlaceResource extends JsonResource
{
    /**
     * @OA\Property(property="id", type="integer", example=123, description="Unique identifier of the trip-place relation")
     * @OA\Property(property="place_id", type="string", example="ChIJ...", description="ID of the place")
     * @OA\Property(property="name", type="string", example="Eiffel Tower", description="Name of the place")
     * @OA\Property(property="category_slug", type="string", example="landmark", description="Category slug")
     * @OA\Property(property="rating", type="number", format="float", example=4.8, description="Rating of the place")
     *
     * @OA\Property(
     * property="place",
     * type="object",
     * description="Detailed information about the place",
     * @OA\Property(property="id", type="string", example="ChIJ..."),
     * @OA\Property(property="google_place_id", type="string", example="ChIJ..."),
     * @OA\Property(property="name", type="string", example="Eiffel Tower"),
     * @OA\Property(property="category_slug", type="string", example="landmark"),
     * @OA\Property(property="rating", type="number", format="float", example=4.8),
     * @OA\Property(property="meta", type="object", nullable=true, description="Additional metadata (JSON)"),
     * @OA\Property(property="lat", type="number", format="float", example=48.8584),
     * @OA\Property(property="lon", type="number", format="float", example=2.2945),
     * @OA\Property(property="distance_m", type="integer", nullable=true, example=500)
     * )
     *
     * @OA\Property(property="status", type="string", example="planned", description="Status of the place in the trip")
     * @OA\Property(property="is_fixed", type="boolean", example=true, description="Is the place fixed in the schedule")
     * @OA\Property(property="day", type="integer", nullable=true, example=1, description="Day number in the itinerary")
     * @OA\Property(property="order_index", type="integer", example=0, description="Order index within the day")
     * @OA\Property(property="note", type="string", nullable=true, example="Buy tickets in advance", description="User note")
     * @OA\Property(property="added_by", type="integer", example=5, description="User ID who added the place")
     *
     * @OA\Property(property="votes", type="array", @OA\Items(type="object"), nullable=true, description="List of votes")
     * @OA\Property(property="avg_score", type="number", format="float", nullable=true, example=4.5, description="Average vote score")
     */
    public function toArray($request): array
    {
        /** @var TripPlace $place */
        $place = $this->resource;

        return [
            'id'            => $place->id,
            'place_id'      => $place->place_id,
            'name'          => $place->name,
            'category_slug' => $place->category_slug,
            'rating'        => $place->rating,

            'place' => [
                'id'              => $place->place_id,
                'google_place_id' => $place->google_place_id,
                'name'            => $place->name,
                'category_slug'   => $place->category_slug,
                'rating'          => $place->rating,
                'meta'            => $place->meta,
                'lat'             => $place->lat,
                'lon'             => $place->lon,
                'distance_m'      => $place->distance_m,
            ],

            'status'      => $place->status,
            'is_fixed'    => $place->is_fixed,
            'day'         => $place->day,
            'order_index' => $place->order_index,
            'note'        => $place->note,
            'added_by'    => $place->added_by,

            'votes'       => $place->votes ?? null,
            'avg_score'   => $place->avg_score ?? null,
        ];
    }
}
