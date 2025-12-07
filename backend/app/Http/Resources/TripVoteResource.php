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
     * @OA\Property(
     * property="avg_score",
     * type="number",
     * format="float",
     * nullable=true,
     * example=4.5,
     * description="Average score calculated from all votes"
     * )
     * @OA\Property(
     * property="votes",
     * type="integer",
     * example=10,
     * description="Total count of votes"
     * )
     */
    public function toArray($request): array
    {
        /** @var TripVote $vote */
        $vote = $this->resource;

        return [
            'avg_score' => $vote->avg_score !== null
                ? (float) $vote->avg_score
                : null,

            'votes' => (int) $vote->votes,
        ];
    }
}
