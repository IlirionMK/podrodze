<?php

namespace App\Services;

use App\Interfaces\PreferenceAggregatorServiceInterface;
use App\Models\Trip;
use App\Models\UserPreference;
use Illuminate\Support\Facades\DB;

class PreferenceAggregatorService implements PreferenceAggregatorServiceInterface
{
    /**
     * Aggregate average category preferences across all trip participants.
     *
     * @param Trip $trip
     * @return array<string, float>  Example: ['museum' => 1.8, 'food' => 2.0]
     */
    public function getGroupPreferences(Trip $trip): array
    {
        // Collect unique user IDs: owner + members
        $userIds = $trip->acceptedMembers()->pluck('users.id')->toArray();
        $userIds[] = $trip->owner_id;
        $userIds = array_unique($userIds);

        if (empty($userIds)) {
            return [];
        }

        // Compute average preference score per category
        return UserPreference::query()
            ->select('categories.slug', DB::raw('AVG(user_preferences.score) as avg_score'))
            ->join('categories', 'categories.id', '=', 'user_preferences.category_id')
            ->whereIn('user_preferences.user_id', $userIds)
            ->groupBy('categories.slug')
            ->get()
            ->mapWithKeys(fn($row) => [
                $row->slug => round((float) $row->avg_score, 2),
            ])
            ->toArray();
    }
}
