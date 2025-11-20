<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use App\DTO\Trip\TripPlace;

/** @mixin TripPlace */
class TripPlaceResource extends JsonResource
{
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
