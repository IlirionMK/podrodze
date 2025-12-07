<?php

namespace App\Http\Resources;

use App\DTO\Trip\Invite;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

/**
 * @OA\Schema(
 * schema="InviteResource",
 * title="Invite Resource",
 * description="Details of a trip invitation"
 * )
 * @mixin Invite
 */
class InviteResource extends JsonResource
{
    /**
     * @OA\Property(property="trip_id", type="integer", example=10, description="ID of the trip")
     * @OA\Property(property="name", type="string", example="Summer Roadtrip", description="Name of the trip")
     * @OA\Property(property="start_date", type="string", format="date-time", nullable=true, example="2025-07-01T00:00:00.000000Z")
     * @OA\Property(property="end_date", type="string", format="date-time", nullable=true, example="2025-07-15T00:00:00.000000Z")
     * @OA\Property(property="role", type="string", example="editor", description="Role assigned in the invitation")
     * @OA\Property(property="status", type="string", example="pending", description="Invitation status")
     *
     * @OA\Property(
     * property="owner",
     * ref="#/components/schemas/UserMiniResource",
     * nullable=true,
     * description="User who sent the invitation"
     * )
     */
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

            'owner' => $invite->owner ? [
                'id'    => $invite->owner->id,
                'name'  => $invite->owner->name,
                'email' => $invite->owner->email,
            ] : null,
        ];
    }
}
