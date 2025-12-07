<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use App\DTO\Preference\Preference;
use OpenApi\Attributes as OA;

/**
 * @OA\Schema(
 * schema="PreferenceResource",
 * title="Preference Resource",
 * description="Structure containing user and category preferences"
 * )
 * @mixin Preference
 */
class PreferenceResource extends JsonResource
{
    /**
     * @OA\Property(
     * property="categories",
     * type="array",
     * description="List of preferred categories",
     * @OA\Items(type="string", example="museums")
     * )
     * @OA\Property(
     * property="user",
     * type="object",
     * description="User-specific preference settings (key-value pairs)",
     * example={"notifications": true, "theme": "dark"}
     * )
     */
    public function toArray($request): array
    {
        return [
            'categories' => array_values($this->resource->categories),
            'user'       => (object) $this->resource->user,
        ];
    }
}
