<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: "UserMiniResource",
    title: "User Mini Resource",
    description: "Minimal representation of a user (admin list)"
)]
class UserMiniResource extends JsonResource
{
    #[OA\Property(property: "id", type: "integer", nullable: true, example: 10, description: "User ID")]
    #[OA\Property(property: "name", type: "string", nullable: true, example: "John Doe", description: "User's full name")]
    #[OA\Property(property: "email", type: "string", format: "email", nullable: true, example: "john@example.com", description: "User's email address")]
    #[OA\Property(property: "role", type: "string", nullable: true, example: "user", description: "User role")]
    #[OA\Property(property: "banned", type: "boolean", example: false, description: "Whether user is banned")]
    #[OA\Property(property: "banned_at", type: "string", format: "date-time", nullable: true, example: "2026-01-23T20:51:41Z", description: "Ban timestamp (null if not banned)")]
    public function toArray($request): array
    {
        $bannedAt = $this->banned_at ?? null;

        return [
            'id'        => $this->id ?? null,
            'name'      => $this->name ?? null,
            'email'     => $this->email ?? null,
            'role'      => $this->role ?? null,
            'banned'    => $bannedAt !== null,
            'banned_at' => $bannedAt?->toISOString(),
        ];
    }
}
