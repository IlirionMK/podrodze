<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\TripPlaceResource;
use App\Http\Resources\TripVoteResource;
use App\Interfaces\PlaceInterface;
use App\Models\Trip;
use App\Models\Place;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class TripPlaceController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected PlaceInterface $placeService
    ) {}

    /**
     * @group Trips / Places
     *
     * Get all places attached to a trip.
     *
     * @authenticated
     * @urlParam trip integer required Trip ID. Example: 1
     * @response 200 scenario="Example" {
     *   "data": [
     *     {"id": 1, "name": "Panorama Sky Bar", "category_slug": "nightlife"},
     *     {"id": 2, "name": "Muzeum Narodowe", "category_slug": "museum"}
     *   ]
     * }
     */
    public function index(Trip $trip): JsonResponse
    {
        $this->authorize('view', $trip);

        $places = $this->placeService->listForTrip($trip);

        return response()->json([
            'data' => TripPlaceResource::collection($places),
        ]);
    }

    /**
     * @group Trips / Places
     *
     * Attach a place to a trip.
     *
     * @authenticated
     * @urlParam trip integer required Trip ID. Example: 1
     * @bodyParam place_id integer required ID of the place. Example: 5
     * @bodyParam status string optional proposed|selected|rejected|planned
     * @bodyParam day integer optional Example: 2
     * @response 201 {"message":"Place added to trip"}
     * @response 409 {"message":"This place is already attached to the trip."}
     */
    public function store(Request $request, Trip $trip): JsonResponse
    {
        $this->authorize('update', $trip);

        $validated = $request->validate([
            'place_id'    => 'required|exists:places,id',
            'status'      => 'nullable|string|in:proposed,selected,rejected,planned',
            'is_fixed'    => 'nullable|boolean',
            'day'         => 'nullable|integer|min:1',
            'order_index' => 'nullable|integer|min:0',
            'note'        => 'nullable|string|max:255',
        ]);

        $result = $this->placeService->attachToTrip($trip, $validated, $request->user());

        return response()->json(['message' => $result['message']], $result['status']);
    }

    /**
     * @group Trips / Places
     *
     * Update a place entry within a trip.
     *
     * @authenticated
     * @urlParam trip integer required Example: 1
     * @urlParam place integer required Example: 5
     * @response 200 {"message":"Trip place updated"}
     * @response 404 {"message":"Place not found in this trip."}
     */
    public function update(Request $request, Trip $trip, Place $place): JsonResponse
    {
        $this->authorize('update', $trip);

        $validated = $request->validate([
            'status'      => 'nullable|string|in:proposed,selected,rejected,planned',
            'is_fixed'    => 'nullable|boolean',
            'day'         => 'nullable|integer|min:1',
            'order_index' => 'nullable|integer|min:0',
            'note'        => 'nullable|string|max:255',
        ]);

        $result = $this->placeService->updateTripPlace($trip, $place, $validated);

        return response()->json(['message' => $result['message']], $result['status']);
    }

    /**
     * @group Trips / Places
     *
     * Remove a place from a trip.
     *
     * @authenticated
     * @urlParam trip integer required Example: 1
     * @urlParam place integer required Example: 5
     * @response 200 {"message":"Place removed from trip"}
     * @response 404 {"message":"Place not found in this trip."}
     */
    public function destroy(Trip $trip, Place $place): JsonResponse
    {
        $this->authorize('update', $trip);

        $result = $this->placeService->detachFromTrip($trip, $place);

        return response()->json(['message' => $result['message']], $result['status']);
    }

    /**
     * @group Trips / Places
     *
     * Submit or update a vote for a place within a trip.
     *
     * @authenticated
     * @urlParam trip integer required Example: 1
     * @urlParam place integer required Example: 5
     * @bodyParam score integer required Example: 4
     * @response 200 {"message":"Vote saved"}
     */
    public function vote(Request $request, Trip $trip, Place $place): JsonResponse
    {
        $this->authorize('view', $trip);

        $validated = $request->validate([
            'score' => ['required', 'integer', 'min:1', 'max:5'],
        ]);

        $vote = $this->placeService->saveTripVote(
            $trip,
            $place,
            $request->user(),
            (int) $validated['score']
        );

        return TripVoteResource::make($vote)
            ->additional(['message' => 'Vote saved'])
            ->response();
    }
}
