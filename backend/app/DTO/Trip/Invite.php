<?php

namespace App\DTO\Trip;

use App\Models\Trip;
use App\Models\User;

final class Invite implements \JsonSerializable
{
    public function __construct(
        public readonly int $trip_id,
        public readonly string $name,
        public readonly ?string $start_date,
        public readonly ?string $end_date,
        public readonly string $role,
        public readonly string $status,
        public readonly User $owner,
    ) {}

    public static function fromModel(Trip $trip): self
    {
        return new self(
            trip_id: $trip->id,
            name: $trip->name,
            start_date: $trip->start_date,
            end_date: $trip->end_date,
            role: $trip->pivot?->role ?? 'member',
            status: $trip->pivot?->status ?? 'pending',
            owner: $trip->owner
        );
    }

    public function jsonSerialize(): array
    {
        return [
            'trip_id'    => $this->trip_id,
            'name'       => $this->name,
            'start_date' => $this->start_date,
            'end_date'   => $this->end_date,
            'role'       => $this->role,
            'status'     => $this->status,
            'owner'      => [
                'id'    => $this->owner->id,
                'name'  => $this->owner->name,
                'email' => $this->owner->email,
            ],
        ];
    }
}
