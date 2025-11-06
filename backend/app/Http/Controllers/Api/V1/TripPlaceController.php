<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Trip;
use App\Models\Place;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class TripPlaceController extends Controller
{
    /**
     * @group Trips / Places
     *
     * Get all places attached to a trip.
     *
     * @urlParam trip_id integer required The ID of the trip.
     * @response 200 scenario="Success" {
     *   "data": [
     *     {"id": 1, "name": "Panorama Sky Bar", "category_slug": "nightlife"},
     *     {"id": 2, "name": "Muzeum Narodowe", "category_slug": "museum"}
     *   ]
     * }
     */
    public function index(Trip $trip)
    {
        $places = $trip->places()
            ->withPivot(['status', 'is_fixed', 'day', 'order_index', 'note', 'added_by'])
            ->get();

        return response()->json([
            'data' => $places,
        ]);
    }

    /**
     * @group Trips / Places
     *
     * Attach a place to a trip.
     *
     * @urlParam trip_id integer required The ID of the trip.
     * @bodyParam place_id integer required The ID of the place to attach.
     * @bodyParam status string optional proposed/selected/rejected/planned
     * @bodyParam is_fixed boolean optional Whether the place is fixed (e.g. hotel, airport)
     * @bodyParam day integer optional Day number in itinerary.
     * @bodyParam order_index integer optional Order of the place in route.
     * @bodyParam note string optional A note for this trip-place link.
     * @response 201 scenario="Created" {"message": "Place added to trip"}
     */
    public function store(Request $request, Trip $trip)
    {
        $validated = $request->validate([
            'place_id' => 'required|exists:places,id',
            'status' => 'nullable|string|in:proposed,selected,rejected,planned',
            'is_fixed' => 'nullable|boolean',
            'day' => 'nullable|integer|min:1',
            'order_index' => 'nullable|integer|min:0',
            'note' => 'nullable|string|max:255',
        ]);

        if ($trip->places()->where('place_id', $validated['place_id'])->exists()) {
            return response()->json([
                'message' => 'This place is already attached to the trip.',
            ], Response::HTTP_CONFLICT);
        }

        $trip->places()->attach($validated['place_id'], [
            'status' => $validated['status'] ?? 'planned',
            'is_fixed' => $validated['is_fixed'] ?? false,
            'day' => $validated['day'] ?? null,
            'order_index' => $validated['order_index'] ?? null,
            'note' => $validated['note'] ?? null,
            'added_by' => $request->user()->id ?? null,
        ]);

        return response()->json(['message' => 'Place added to trip'], Response::HTTP_CREATED);
    }

    /**
     * @group Trips / Places
     *
     * Update metadata for a place in a trip.
     *
     * @urlParam trip_id integer required The ID of the trip.
     * @urlParam place_id integer required The ID of the place.
     * @bodyParam status string optional proposed/selected/rejected/planned
     * @bodyParam is_fixed boolean optional Mark as fixed (stałe)
     * @bodyParam day integer optional Assign to day number
     * @bodyParam order_index integer optional Change order
     * @bodyParam note string optional Add or update note
     * @response 200 scenario="Updated" {"message": "Trip place updated"}
     */
    public function update(Request $request, Trip $trip, Place $place)
    {
        $validated = $request->validate([
            'status' => 'nullable|string|in:proposed,selected,rejected,planned',
            'is_fixed' => 'nullable|boolean',
            'day' => 'nullable|integer|min:1',
            'order_index' => 'nullable|integer|min:0',
            'note' => 'nullable|string|max:255',
        ]);

        if (! $trip->places()->where('place_id', $place->id)->exists()) {
            return response()->json([
                'message' => 'Place not found in this trip.',
            ], Response::HTTP_NOT_FOUND);
        }

        $trip->places()->updateExistingPivot($place->id, array_filter($validated, fn($v) => !is_null($v)));

        return response()->json(['message' => 'Trip place updated']);
    }

    /**
     * @group Trips / Places
     *
     * Remove a place from a trip.
     *
     * @urlParam trip_id integer required The ID of the trip.
     * @urlParam place_id integer required The ID of the place.
     * @response 200 scenario="Removed" {"message": "Place removed from trip"}
     */
    public function destroy(Trip $trip, Place $place)
    {
        if (! $trip->places()->where('place_id', $place->id)->exists()) {
            return response()->json([
                'message' => 'Place not found in this trip.',
            ], Response::HTTP_NOT_FOUND);
        }

        $trip->places()->detach($place->id);

        return response()->json(['message' => 'Place removed from trip']);
    }

    /**
     * @group Trips / Places
     *
     * Vote for a place in a trip.
     *
     * @urlParam trip_id integer required The ID of the trip.
     * @urlParam place_id integer required The ID of the place.
     * @bodyParam score integer required The vote score (1–5).
     * @response 200 scenario="Success" {"message": "Vote saved", "avg_score": 4.5, "votes": 3}
     */
    public function vote(Request $request, Trip $trip, Place $place)
    {
        $validated = $request->validate([
            'score' => ['required', 'integer', 'min:1', 'max:5'],
        ]);

        DB::table('trip_place_votes')->updateOrInsert(
            [
                'trip_id' => $trip->id,
                'place_id' => $place->id,
                'user_id' => $request->user()->id,
            ],
            [
                'score' => $validated['score'],
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        $agg = DB::table('trip_place_votes')
            ->selectRaw('AVG(score) as avg_score, COUNT(*) as votes')
            ->where('trip_id', $trip->id)
            ->where('place_id', $place->id)
            ->first();

        return response()->json([
            'message' => 'Vote saved',
            'avg_score' => round($agg->avg_score ?? 0, 2),
            'votes' => (int)($agg->votes ?? 0),
        ]);
    }
}
