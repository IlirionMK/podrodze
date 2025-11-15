<?php

namespace App\DTO\Preference;

final class Preference
{
    /**
     * @param array<int, array{slug: string, name: string}> $categories
     * @param array<string, int> $user
     */
    public function __construct(
        public readonly array $categories,
        public readonly array $user
    ) {}
}
