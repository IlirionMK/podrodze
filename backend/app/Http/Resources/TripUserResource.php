<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class TripUserResource extends JsonResource
{
    public function toArray($request): array
    {
        $pivot = $this->pivot ?? null;

        return [
            'id'        => $this->id,
            'name'      => $this->name,
            'email'     => $this->email,

            'is_owner'  => isset($this->is_owner)
                ? (bool) $this->is_owner
                : ($pivot?->role === 'owner'),

            'role'      => $pivot?->role ?? 'member',
            'status'    => $pivot?->status ?? 'accepted',

            'pivot'     => $pivot ? [
                'role'   => $pivot->role,
                'status' => $pivot->status,
            ] : null,
        ];
    }
}
