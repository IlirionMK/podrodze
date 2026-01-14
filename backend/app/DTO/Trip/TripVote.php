<?php

namespace App\DTO\Trip;

final class TripVote implements \JsonSerializable
{
    public function __construct(
        public readonly int $place_id,
        public readonly ?int $my_score,
        public readonly ?float $avg_score,
        public readonly int $votes
    ) {}


    public static function fromAggregate(int $placeId, ?int $myScore, ?object $row): self
    {
        $avg = $row->avg_score ?? null;

        return new self(
            place_id:  $placeId,
            my_score:  $myScore,
            avg_score: $avg === null ? null : round((float) $avg, 2),
            votes:     (int) ($row->votes ?? 0),
        );
    }

    public function jsonSerialize(): array
    {
        return [
            'place_id'  => $this->place_id,
            'my_score'  => $this->my_score,
            'avg_score' => $this->avg_score,
            'votes'     => $this->votes,
        ];
    }
}
