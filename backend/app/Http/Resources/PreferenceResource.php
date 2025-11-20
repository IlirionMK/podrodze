<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use App\DTO\Preference\Preference;

/** @mixin Preference */
class PreferenceResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'categories' => array_values($this->resource->categories),
            'user'       => (object) $this->resource->user,
        ];
    }
}
