<?php

namespace App\DTO\Preference;

final class Preference implements \JsonSerializable
{
    public function __construct(
        public readonly array $categories,
        public readonly array $user
    ) {}

    public function jsonSerialize(): array
    {
        return [
            'categories' => $this->categories,
            'user'       => $this->user,
        ];
    }
}
