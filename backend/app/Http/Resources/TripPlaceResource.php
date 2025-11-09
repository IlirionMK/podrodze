<?php


namespace App\Http\Resources;

use App\DTO\Trip\TripPlace;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin TripPlace */
class TripPlaceResource extends JsonResource
{
    public function toArray($request): array
    {
        /** @var TripPlace $this ->resource */
        return $this->resource->jsonSerialize();
    }
}
