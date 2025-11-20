<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class TripResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'             => $this->id,
            'name'           => $this->name,

            'start_date'     => optional($this->start_date)->toISOString(),
            'end_date'       => optional($this->end_date)->toISOString(),

            'start_latitude'  => $this->start_latitude,
            'start_longitude' => $this->start_longitude,

            'owner_id'       => $this->owner_id,
            'owner'          => new UserMiniResource($this->whenLoaded('owner')),
            'members'        => TripUserResource::collection($this->whenLoaded('members')),

            'created_at'     => optional($this->created_at)->toISOString(),
            'updated_at'     => optional($this->updated_at)->toISOString(),
        ];
    }
}
