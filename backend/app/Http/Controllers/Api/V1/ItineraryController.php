<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Trip;
use App\Models\UserPreference;
use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class ItineraryController extends Controller
{
    use AuthorizesRequests;

    /**
     * @group Trips / Itinerary
     *
     * Generate trip itinerary based on group preferences
     *
     * Aggregates preferences of all accepted trip members and returns
     * average scores per category.
     *
     * @authenticated
     * @urlParam trip int required Trip ID. Example: 1
     *
     * @response 200 [
     *   {
     *     "category": "museum",
     *     "avg_score": 1.8,
     *     "votes": 5
     *   },
     *   {
     *     "category": "food",
     *     "avg_score": 1.4,
     *     "votes": 5
     *   }
     * ]
     */
    public function index(Request $request, Trip $trip)
    {
        $this->authorize('view', $trip);

        $acceptedUserIds = $trip->members()
            ->wherePivot('status', 'accepted')
            ->pluck('users.id');

        if ($acceptedUserIds->isEmpty()) {
            return response()->json([
                'message' => 'No accepted members for this trip yet.'
            ], 200);
        }

        $aggregated = UserPreference::query()
            ->selectRaw('category_id, AVG(score) as avg_score, COUNT(*) as votes')
            ->whereIn('user_id', $acceptedUserIds)
            ->groupBy('category_id')
            ->with('category:id,slug,translations')
            ->get()
            ->filter(fn ($pref) => $pref->category)
            ->map(function ($pref) {
                return [
                    'category'  => $pref->category->slug,
                    'avg_score' => round($pref->avg_score, 2),
                    'votes'     => $pref->votes,
                    'name'      => $pref->category->translations['en']
                        ?? $pref->category->slug,
                ];
            })
            ->sortByDesc('avg_score')
            ->values();

        return response()->json($aggregated);
    }
}
