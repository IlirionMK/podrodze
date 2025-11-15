<?php

namespace App\Http\Resources;

use App\DTO\Trip\Invite;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Invite */
class InviteResource extends JsonResource
{
    public function toArray($request): array
    {
        /** @var Invite $invite */
        $invite = $this->resource;

        return [
            'trip_id'    => $invite->trip_id,
            'name'       => $invite->name,
            'start_date' => $invite->start_date,
            'end_date'   => $invite->end_date,
            'role'       => $invite->role,
            'status'     => $invite->status,
            'owner'      => [
                'id'    => $invite->owner->id,
                'name'  => $invite->owner->name,
                'email' => $invite->owner->email,
            ],
        ];
    }
}
