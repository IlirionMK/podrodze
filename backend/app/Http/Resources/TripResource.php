<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

/**
 * @OA\Schema(
 * schema="TripResource",
 * title="Trip Resource",
 * description="Data structure for the Trip entity."
 * )
 */
class TripResource extends JsonResource
{
    /**
     * @OA\Property(property="id", type="integer", example=1, description="Unique trip identifier")
     * @OA\Property(property="name", type="string", example="Hike to Altai Mountains", description="Name of the trip")
     *
     * @OA\Property(property="start_date", type="string", format="date-time", nullable=true, example="2025-06-10T00:00:00.000000Z", description="Trip start date (ISO 8601)")
     * @OA\Property(property="end_date", type="string", format="date-time", nullable=true, example="2025-06-17T00:00:00.000000Z", description="Trip end date (ISO 8601)")
     *
     * @OA\Property(property="start_latitude", type="number", format="float", nullable=true, example="55.7558", description="Latitude of the starting point")
     * @OA\Property(property="start_longitude", type="number", format="float", nullable=true, example="37.6176", description="Longitude of the starting point")
     *
     * @OA\Property(property="owner_id", type="integer", example=5, description="ID of the trip owner")
     *
     * @OA\Property(property="owner", ref="#/components/schemas/UserMiniResource", description="The trip owner object (mini format)")
     * @OA\Property(property="members", type="array", @OA\Items(ref="#/components/schemas/TripUserResource"), description="List of trip participants")
     *
     * @OA\Property(property="created_at", type="string", format="date-time", example="2025-05-01T12:00:00.000000Z", description="Creation date (ISO 8601)")
     * @OA\Property(property="updated_at", type="string", format="date-time", example="2025-05-01T12:00:00.000000Z", description="Last update date (ISO 8601)")
     *
     */
    public function toArray($request): array
    {
        return [
            'id'             => $this->id,
            'name'           => $this->name,

            'start_date'     => optional($this->start_date)->toISOString(),
            'end_date'       => optional($this->end_date)->toISOString(),

            'start_latitude'  => $this->start_latitude,
            'start_longitude' => $this->start_longitude,

            'owner_id'       => $this->owner_id,
            'owner'          => new UserMiniResource($this->whenLoaded('owner')),
            'members'        => TripUserResource::collection($this->whenLoaded('members')),

            'created_at'     => optional($this->created_at)->toISOString(),
            'updated_at'     => optional($this->updated_at)->toISOString(),
        ];
    }
}
