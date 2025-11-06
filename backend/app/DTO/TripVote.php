<?php

namespace App\DTO;

final class TripVote implements \JsonSerializable
{
    public function __construct(
        public readonly float $avg_score,
        public readonly int $votes
    ) {}

    public static function fromAggregate(?object $row): self
    {
        return new self(
            avg_score: round((float)($row->avg_score ?? 0), 2),
            votes: (int)($row->votes ?? 0)
        );
    }

    public function jsonSerialize(): array
    {
        return [
            'avg_score' => $this->avg_score,
            'votes'     => $this->votes,
        ];
    }
}
