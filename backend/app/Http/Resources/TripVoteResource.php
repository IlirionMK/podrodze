<?php

namespace App\Http\Resources;

use App\DTO\Trip\TripVote;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

/**
 * @OA\Schema(
 * schema="TripVoteResource",
 * title="Trip Vote Resource",
 * description="Response containing vote statistics for a trip place"
 * )
 * @mixin TripVote
 */
class TripVoteResource extends JsonResource
{
    /**
     * @OA\Property(property="place_id", type="integer", example=123)
     * @OA\Property(property="my_score", type="integer", nullable=true, minimum=1, maximum=5, example=4)
     * @OA\Property(property="avg_score", type="number", format="float", nullable=true, example=4.5)
     * @OA\Property(property="votes", type="integer", example=10)
     */
    public function toArray($request): array
    {
        /** @var TripVote $vote */
        $vote = $this->resource;

        return [
            'place_id'  => (int) $vote->place_id,
            'my_score'  => $vote->my_score === null ? null : (int) $vote->my_score,
            'avg_score' => $vote->avg_score === null ? null : (float) $vote->avg_score,
            'votes'     => (int) $vote->votes,
        ];
    }
}
