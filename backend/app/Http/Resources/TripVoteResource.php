<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use App\DTO\TripVote;

/** @mixin TripVote */
class TripVoteResource extends JsonResource
{
    public function toArray($request): array
    {
        /** @var TripVote $this->resource */
        return $this->resource->jsonSerialize();
    }
}
