<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ItineraryResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'trip_id'    => $this->resource->trip_id,
            'day_count'  => $this->resource->day_count,
            'schedule'   => $this->resource->schedule,
            'cache_info' => $this->resource->cache_info,
        ];
    }
}
