<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class TripUserResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'        => $this->id,
            'name'      => $this->name,
            'email'     => $this->email,
            'is_owner'  => $this->is_owner ?? false,
            'pivot' => [
                'role'   => $this->pivot->role ?? 'member',
                'status' => $this->pivot->status ?? 'accepted',
            ],
        ];
    }
}
