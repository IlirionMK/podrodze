<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use App\DTO\Trip\TripPlace;
use OpenApi\Attributes as OA;

/**
 * @OA\Schema(
 *   schema="TripPlaceResource",
 *   title="Trip Place Resource",
 *   description="Resource representing a place within a trip"
 * )
 * @mixin TripPlace
 */
class TripPlaceResource extends JsonResource
{
    public function toArray($request): array
    {
        /** @var TripPlace $tp */
        $tp = $this->resource;

        $myScore = null;
        $avgScore = null;
        $votes = null;

        if (is_object($tp)) {
            if (property_exists($tp, 'my_score')) {
                $myScore = $tp->my_score;
            }
            if (property_exists($tp, 'avg_score')) {
                $avgScore = $tp->avg_score;
            }
            if (property_exists($tp, 'votes')) {
                $votes = $tp->votes;
            }

            if ($myScore === null && property_exists($tp, 'vote') && is_array($tp->vote)) {
                $myScore = $tp->vote['my_score'] ?? null;
                $avgScore = $tp->vote['avg_score'] ?? null;
                $votes = $tp->vote['votes'] ?? null;
            }

            if ($myScore === null && property_exists($tp, 'vote_summary') && is_array($tp->vote_summary)) {
                $myScore = $tp->vote_summary['my_score'] ?? null;
                $avgScore = $tp->vote_summary['avg_score'] ?? null;
                $votes = $tp->vote_summary['votes'] ?? null;
            }
        }

        return [
            'id' => $tp->id,

            'place' => [
                'id'            => $tp->place['id'],
                'name'          => $tp->place['name'],
                'category_slug' => $tp->place['category_slug'],
                'lat'           => $tp->place['lat'],
                'lon'           => $tp->place['lon'],
            ],

            'status'      => $tp->status,
            'is_fixed'    => $tp->is_fixed,
            'day'         => $tp->day,
            'order_index' => $tp->order_index,
            'note'        => $tp->note,
            'added_by'    => $tp->added_by,

            'my_score'  => $myScore,
            'avg_score' => $avgScore,
            'votes'     => $votes,
        ];
    }
}
