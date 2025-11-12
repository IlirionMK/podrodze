<?php

namespace App\DTO\Preference;

/**
 * Data Transfer Object for user preferences and category list.
 *
 * @property-read array<int, array{slug: string, name: string}> $categories
 * @property-read array<string, int> $user
 */
final class Preference implements \JsonSerializable
{
    /**
     * @param array<int, array{slug: string, name: string}> $categories
     * @param array<string, int> $user
     */
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
