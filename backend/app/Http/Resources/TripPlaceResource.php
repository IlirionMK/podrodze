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
        /** @var TripPlace $place */
        $place = $this->resource;

        return [
            'id'            => $place->id,
            'name'          => $place->name,
            'category_slug' => $place->category_slug,
            'rating'        => $place->rating,

            'status'        => $place->status,
            'is_fixed'      => $place->is_fixed,
            'day'           => $place->day,
            'order_index'   => $place->order_index,
            'note'          => $place->note,
            'added_by'      => $place->added_by,
        ];
    }
}
