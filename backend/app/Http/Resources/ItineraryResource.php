<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use App\DTO\Itinerary\Itinerary;

class ItineraryResource extends JsonResource
{
    /** @var Itinerary */
    public $resource;

    public function toArray($request): array
    {
        return $this->resource->jsonSerialize();
    }
}
