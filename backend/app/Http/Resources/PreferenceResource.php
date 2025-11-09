<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use App\DTO\Preference\Preference;

class PreferenceResource extends JsonResource
{
    /** @var Preference */
    public $resource;

    public function toArray($request): array
    {
        return [
            'categories' => $this->resource->categories,
            'user' => $this->resource->user,
        ];
    }
}
