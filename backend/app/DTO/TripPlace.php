<?php

namespace App\DTO;

use App\Models\Place;

final class TripPlace implements \JsonSerializable
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly ?string $category_slug,
        public readonly ?float $rating,
        // pivot:
        public readonly ?string $status,
        public readonly bool $is_fixed,
        public readonly ?int $day,
        public readonly ?int $order_index,
        public readonly ?string $note,
        public readonly ?int $added_by,
    ) {}

    public static function fromModel(Place $place): self
    {
        $p = $place->pivot ?? null;

        return new self(
            id: $place->id,
            name: $place->name,
            category_slug: $place->category_slug,
            rating: $place->rating,
            status: $p->status ?? null,
            is_fixed: (bool)($p->is_fixed ?? false),
            day: $p->day ?? null,
            order_index: $p->order_index ?? null,
            note: $p->note ?? null,
            added_by: $p->added_by ?? null,
        );
    }

    public function jsonSerialize(): array
    {
        return [
            'id'            => $this->id,
            'name'          => $this->name,
            'category_slug' => $this->category_slug,
            'rating'        => $this->rating,
            'pivot' => [
                'status'      => $this->status,
                'is_fixed'    => $this->is_fixed,
                'day'         => $this->day,
                'order_index' => $this->order_index,
                'note'        => $this->note,
                'added_by'    => $this->added_by,
            ],
        ];
    }
}
