<?php


namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use App\DTO\TripPlace;

/** @mixin TripPlace */
class TripPlaceResource extends JsonResource
{
    public function toArray($request): array
    {
        /** @var TripPlace $this ->resource */
        return $this->resource->jsonSerialize();
    }
}
