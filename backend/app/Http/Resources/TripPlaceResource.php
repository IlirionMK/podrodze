<?php

namespace App\Http\Resources;

use App\DTO\Trip\TripPlace;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin TripPlace */
class TripPlaceResource extends JsonResource
{
    public function toArray($request): array
    {
        /** @var TripPlace $place */
        $place = $this->resource;

        return [
            'id'            => $place->id,
            'name'          => $place->name,
            'category_slug' => $place->category_slug,
            'rating'        => $place->rating,
            'pivot' => [
                'status'      => $place->status,
                'is_fixed'    => $place->is_fixed,
                'day'         => $place->day,
                'order_index' => $place->order_index,
                'note'        => $place->note,
                'added_by'    => $place->added_by,
            ],
        ];
    }
}
