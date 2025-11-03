<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Trip;
use App\Models\Place;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

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
        $places = $trip->places()->get();

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
     * @bodyParam order_index integer optional Order of the place in route.
     * @bodyParam note string optional A note for this trip-place link.
     * @response 201 scenario="Created" {"message": "Place added to trip"}
     */
    public function store(Request $request, Trip $trip)
    {
        $validated = $request->validate([
            'place_id' => 'required|exists:places,id',
            'order_index' => 'nullable|integer',
            'note' => 'nullable|string|max:255',
        ]);

        if ($trip->places()->where('place_id', $validated['place_id'])->exists()) {
            return response()->json([
                'message' => 'This place is already attached to the trip.',
            ], Response::HTTP_CONFLICT);
        }

        $trip->places()->attach($validated['place_id'], [
            'order_index' => $validated['order_index'] ?? null,
            'note' => $validated['note'] ?? null,
        ]);

        return response()->json(['message' => 'Place added to trip'], Response::HTTP_CREATED);
    }

    /**
     * @group Trips / Places
     *
     * Detach a place from a trip.
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
}
