<?php

namespace App\Http\Resources;

use App\DTO\Trip\TripVote;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin TripVote */
class TripVoteResource extends JsonResource
{
    public function toArray($request): array
    {
        /** @var TripVote $vote */
        $vote = $this->resource;

        return [
            'avg_score' => $vote->avg_score,
            'votes'     => $vote->votes,
        ];
    }
}
