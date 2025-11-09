<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use App\DTO\Itinerary\Itinerary;
use App\DTO\Itinerary\ItineraryDay;
use App\DTO\Itinerary\ItineraryPlace;

class ItineraryResource extends JsonResource
{
    /** @var Itinerary */
    public $resource;

    public function toArray($request): array
    {
        return [
            'trip_id' => $this->resource->trip_id,
            'day_count' => $this->resource->day_count,
            'schedule' => array_map(
                fn (ItineraryDay $day) => [
                    'day' => $day->day,
                    'places' => array_map(
                        fn (ItineraryPlace $place) => [
                            'id' => $place->id,
                            'name' => $place->name,
                            'category_slug' => $place->category_slug,
                            'score' => $place->score,
                            'distance_m' => $place->distance_m,
                        ],
                        $day->places
                    ),
                ],
                $this->resource->schedule
            ),
            'cache_info' => $this->resource->cache_info,
        ];
    }
}
