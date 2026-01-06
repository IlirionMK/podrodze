<?php

namespace App\Http\Resources;

use App\DTO\Trip\TripPlaceVoteSummary;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin TripPlaceVoteSummary */
class TripPlaceVoteSummaryResource extends JsonResource
{
    public function toArray($request): array
    {
        /** @var TripPlaceVoteSummary $dto */
        $dto = $this->resource;

        return [
            'place_id'  => $dto->place_id,
            'avg_score' => $dto->avg_score,
            'votes'     => $dto->votes,
            'my_score'  => $dto->my_score,
        ];
    }
}
