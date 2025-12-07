<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

/**
 * @OA\Schema(
 * schema="TripUserResource",
 * title="Trip User Resource",
 * description="User details within the context of a specific trip"
 * )
 */
class TripUserResource extends JsonResource
{
    /**
     * @OA\Property(property="id", type="integer", example=15, description="User ID")
     * @OA\Property(property="name", type="string", example="Jane Doe", description="User's name")
     * @OA\Property(property="email", type="string", format="email", example="jane@example.com", description="User's email")
     *
     * @OA\Property(property="is_owner", type="boolean", example=false, description="Indicates if this user is the owner of the trip")
     * @OA\Property(property="role", type="string", example="member", description="Role in the trip (owner, member, etc.)")
     * @OA\Property(property="status", type="string", example="accepted", description="Participation status (pending, accepted, etc.)")
     *
     * @OA\Property(
     * property="pivot",
     * type="object",
     * nullable=true,
     * description="Raw pivot data (optional)",
     * @OA\Property(property="role", type="string", example="member"),
     * @OA\Property(property="status", type="string", example="accepted")
     * )
     */
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
