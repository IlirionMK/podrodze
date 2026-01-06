<?php

namespace App\DTO\Trip;

use App\Models\Place;

/**
 * TripPlace DTO
 *
 * Represents a Place in the context of a Trip.
 * Contains a slim Place projection (for map/UI) and pivot-related data.
 */
final class TripPlace
{
    public function __construct(
        public readonly int $id,

        /**
         * Slim place projection.
         *
         * @var array{
         *   id: int,
         *   name: string,
         *   category_slug: string|null,
         *   lat: float|null,
         *   lon: float|null
         * }
         */
        public readonly array $place,

        // Pivot fields (trip_place)
        public readonly ?string $status,
        public readonly bool $is_fixed,
        public readonly ?int $day,
        public readonly ?int $order_index,
        public readonly ?string $note,
        public readonly ?int $added_by,
    ) {}

    /**
     * Create TripPlace DTO from Place model with pivot.
     */
    public static function fromModel(Place $place): self
    {
        $p = $place->pivot;

        return new self(
            id: $place->id,

            place: [
                'id'            => $place->id,
                'name'          => $place->name,
                'category_slug' => $place->category_slug,

                // IMPORTANT: PostGIS POINT (lon lat)
                'lat' => $place->lat,
                'lon' => $place->lon,

            ],

            status: $p->status ?? null,
            is_fixed: (bool) ($p->is_fixed ?? false),
            day: $p->day ?? null,
            order_index: $p->order_index ?? null,
            note: $p->note ?? null,
            added_by: $p->added_by ?? null,
        );
    }
}
