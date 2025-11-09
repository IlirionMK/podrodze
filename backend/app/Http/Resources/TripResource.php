<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class TripResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'            => $this->id,
            'name'          => $this->name,
            'start_date'    => $this->start_date,
            'end_date'      => $this->end_date,
            'start_latitude'  => $this->start_latitude,
            'start_longitude' => $this->start_longitude,
            'owner_id'      => $this->owner_id,
            'owner'         => new UserMiniResource($this->whenLoaded('owner')),
            'members'       => TripUserResource::collection($this->whenLoaded('members')),
            'created_at'    => $this->created_at,
            'updated_at'    => $this->updated_at,
        ];
    }
}
