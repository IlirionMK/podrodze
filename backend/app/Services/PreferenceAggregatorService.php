<?php

namespace App\Services;

use App\Models\Trip;
use App\Models\UserPreference;
use Illuminate\Support\Facades\DB;

class PreferenceAggregatorService
{
    /**
     * Calculate average preferences of all trip members (including owner).
     *
     * @param Trip $trip
     * @return array<string, float>
     */
    public function getGroupPreferences(Trip $trip): array
    {
        $userIds = $trip->members()->pluck('users.id')->toArray();
        $userIds[] = $trip->owner_id;
        $userIds = array_unique($userIds);

        if (empty($userIds)) {
            return [];
        }

        $results = UserPreference::select(
            'categories.slug',
            DB::raw('AVG(user_preferences.score) as avg_score')
        )
            ->join('categories', 'categories.id', '=', 'user_preferences.category_id')
            ->whereIn('user_preferences.user_id', $userIds)
            ->groupBy('categories.slug')
            ->get()
            ->mapWithKeys(fn ($row) => [
                $row->slug => round((float) $row->avg_score, 2)
            ]);

        return $results->toArray();
    }
}
