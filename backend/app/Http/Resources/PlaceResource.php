<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlaceResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'            => $this->id,
            'google_place_id' => $this->google_place_id,
            'name'          => $this->name,
            'category_slug' => $this->category_slug,
            'rating'        => $this->rating,
            'meta'          => $this->meta,

            'lat' => $this->lat,
            'lon' => $this->lon,
            'distance_m'    => isset($this->distance_m)
                ? round((float) $this->distance_m, 1)
                : null,

            'created_at'    => $this->created_at,
            'updated_at'    => $this->updated_at,
        ];
    }
}
