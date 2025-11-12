<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class InviteResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'trip_id'    => $this->trip_id,
            'trip_name'  => $this->name,
            'start_date' => $this->start_date,
            'end_date'   => $this->end_date,
            'role'       => $this->role,
            'status'     => $this->status,
            'owner'      => new UserMiniResource($this->owner),
        ];
    }
}
