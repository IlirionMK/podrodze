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
        return [
            'trip_id' => $this->resource->trip_id,
            'day_count' => $this->resource->day_count,
            'schedule' => $this->resource->schedule,
            'cache_info' => $this->resource->cache_info,
        ];
    }
}
