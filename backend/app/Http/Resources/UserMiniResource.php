<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class UserMiniResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'    => $this->id ?? null,
            'name'  => $this->name ?? null,
            'email' => $this->email ?? null,
        ];
    }
}
