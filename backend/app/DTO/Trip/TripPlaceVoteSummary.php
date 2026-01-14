<?php

namespace App\DTO\Trip;

final class TripPlaceVoteSummary implements \JsonSerializable
{
    public function __construct(
        public readonly int $place_id,
        public readonly ?float $avg_score,
        public readonly int $votes,
        public readonly ?int $my_score,
    ) {}

    public static function fromRow(object $row): self
    {
        $avg = $row->avg_score ?? null;

        return new self(
            place_id: (int) $row->place_id,
            avg_score: $avg === null ? null : round((float) $avg, 2),
            votes: (int) ($row->votes ?? 0),
            my_score: $row->my_score === null ? null : (int) $row->my_score,
        );
    }

    public function jsonSerialize(): array
    {
        return [
            'place_id'  => $this->place_id,
            'avg_score' => $this->avg_score,
            'votes'     => $this->votes,
            'my_score'  => $this->my_score,
        ];
    }
}
