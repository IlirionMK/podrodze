<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class InviteResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'trip_id'     => $this->trip_id,
            'trip_name'   => $this->trip_name ?? $this->name ?? null,
            'start_date'  => $this->start_date,
            'end_date'    => $this->end_date,
            'role'        => $this->role,
            'status'      => $this->status,
            'owner'       => new UserMiniResource($this->whenLoaded('owner')),
            'invited_user' => $this->when(
                isset($this->invited_user),
                fn() => [
                    'id'    => $this->invited_user['id'] ?? null,
                    'name'  => $this->invited_user['name'] ?? null,
                    'email' => $this->invited_user['email'] ?? null,
                ]
            ),
        ];
    }
}
