<?php

namespace App\Interfaces;

use App\DTO\Preference\Preference;
use App\Models\User;

interface PreferenceServiceInterface
{
    public function getPreferences(User $user): Preference;

    /**
     * @param User $user
     * @param array<string,int> $preferences
     * @return array{status: string}
     */
    public function updatePreferences(User $user, array $preferences): array;
}
