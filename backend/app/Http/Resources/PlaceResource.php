<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PlaceResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'              => $this->id,
            'google_place_id' => $this->google_place_id,
            'name'            => $this->name,
            'category_slug'   => $this->category_slug,
            'rating'          => $this->rating,

            'meta'            => is_array($this->meta) ? $this->meta : json_decode($this->meta, true),

            'lat'             => $this->lat ?? null,
            'lon'             => $this->lon ?? null,

            'distance_m'      => isset($this->distance_m)
                ? round((float) $this->distance_m, 1)
                : null,

            'created_at'      => optional($this->created_at)->toISOString(),
            'updated_at'      => optional($this->updated_at)->toISOString(),
        ];
    }
}
