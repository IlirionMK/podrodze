<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

final class SuggestedPlaceResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'source'            => $this->source,
            'internal_place_id' => $this->internalPlaceId,
            'external_id'       => $this->externalId,
            'name'              => $this->name,
            'category'          => $this->category,
            'rating'            => $this->rating,
            'reviews_count'     => $this->reviewsCount,
            'location' => [
                'lat' => $this->lat,
                'lon' => $this->lon,
            ],
            'distance_m'        => $this->distanceMeters,
            'estimated_visit_minutes' => $this->estimatedVisitMinutes,
            'score'             => $this->score,
            'reason'            => $this->reason,
            'actions' => [
                'add_payload' => $this->addPayload,
            ],
        ];
    }
}
