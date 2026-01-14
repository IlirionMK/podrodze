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
    public function toArray($request): array
    {
        /** @var TripPlace $tp */
        $tp = $this->resource;

        return [
            'id' => $tp->id,

            'place' => [
                'id'            => $tp->place['id'],
                'name'          => $tp->place['name'],
                'category_slug' => $tp->place['category_slug'],
                'lat'           => $tp->place['lat'],
                'lon'           => $tp->place['lon'],
            ],

            'status'      => $tp->status,
            'is_fixed'    => $tp->is_fixed,
            'day'         => $tp->day,
            'order_index' => $tp->order_index,
            'note'        => $tp->note,
            'added_by'    => $tp->added_by,
        ];
    }
}
