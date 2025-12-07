<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

/**
 * @OA\Schema(
 * schema="UserMiniResource",
 * title="User Mini Resource",
 * description="Minimal representation of a user"
 * )
 */
class UserMiniResource extends JsonResource
{
    /**
     * @OA\Property(property="id", type="integer", nullable=true, example=10, description="User ID")
     * @OA\Property(property="name", type="string", nullable=true, example="John Doe", description="User's full name")
     * @OA\Property(property="email", type="string", format="email", nullable=true, example="john@example.com", description="User's email address")
     */
    public function toArray($request): array
    {
        return [
            'id'    => $this->id ?? null,
            'name'  => $this->name ?? null,
            'email' => $this->email ?? null,
        ];
    }
}
