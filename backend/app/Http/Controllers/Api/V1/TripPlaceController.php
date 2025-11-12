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
use DomainException;
use Illuminate\Database\Eloquent\ModelNotFoundException;

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
     * Retrieve all places currently associated with a given trip.
     *
     * @authenticated
     * @urlParam trip integer required Trip ID. Example: 1
     * @response 200 scenario="Example" {
     *   "data": [
     *     {
     *       "id": 1,
     *       "name": "Panorama Sky Bar",
     *       "category_slug": "nightlife",
     *       "rating": 4.7,
     *       "pivot": {
     *         "status": "planned",
     *         "is_fixed": false,
     *         "day": 1,
     *         "order_index": 0,
     *         "note": null,
     *         "added_by": 3
     *       }
     *     }
     *   ]
     * }
     */
    public function index(Trip $trip): JsonResponse
    {
        $this->authorize('view', $trip);
        $places = $this->placeService->listForTrip($trip);
        return response()->json(['data' => TripPlaceResource::collection($places)]);
    }

    /**
     * @group Trips / Places
     *
     * Attach a place to a trip.
     *
     * Add an existing place to a specific trip with optional planning details.
     *
     * @authenticated
     * @urlParam trip integer required Trip ID. Example: 1
     * @bodyParam place_id integer required ID of the place to attach. Example: 5
     * @bodyParam status string optional Status of the place in the trip. Example: planned
     * @bodyParam is_fixed boolean optional Whether this place is fixed in itinerary. Example: false
     * @bodyParam day integer optional Day number in the trip. Example: 2
     * @bodyParam order_index integer optional Order index within the day. Example: 0
     * @bodyParam note string optional User note about this place. Example: Must visit before 5 PM
     * @response 201 scenario="Created" {"message":"Place added to trip"}
     * @response 409 scenario="Duplicate" {"message":"This place is already attached to the trip."}
     * @response 404 scenario="Not found" {"message":"Place #123 not found."}
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

        try {
            $dto = $this->placeService->attachToTrip($trip, $validated, $request->user());
            return (new TripPlaceResource($dto))
                ->additional(['message' => 'Place added to trip'])
                ->response()
                ->setStatusCode(201);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        }
    }

    /**
     * @group Trips / Places
     *
     * Update a place entry within a trip.
     *
     * Modify the attributes (day, order, status, note) of an existing trip place.
     *
     * @authenticated
     * @urlParam trip integer required Trip ID. Example: 1
     * @urlParam place integer required Place ID. Example: 5
     * @bodyParam status string optional New status for the place. Example: selected
     * @bodyParam is_fixed boolean optional Mark as fixed. Example: true
     * @bodyParam day integer optional Change the day number. Example: 3
     * @bodyParam order_index integer optional Reorder within the day. Example: 2
     * @bodyParam note string optional Update note. Example: "Lunch stop"
     * @response 200 scenario="Updated" {"message":"Trip place updated"}
     * @response 404 scenario="Not found" {"message":"Place not found in this trip."}
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

        try {
            $dto = $this->placeService->updateTripPlace($trip, $place, $validated);
            return (new TripPlaceResource($dto))
                ->additional(['message' => 'Trip place updated'])
                ->response();
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        }
    }

    /**
     * @group Trips / Places
     *
     * Remove a place from a trip.
     *
     * Detach an existing place from the given trip.
     *
     * @authenticated
     * @urlParam trip integer required Trip ID. Example: 1
     * @urlParam place integer required Place ID. Example: 5
     * @response 200 scenario="Deleted" {"message":"Place removed from trip"}
     * @response 404 scenario="Not found" {"message":"Place not found in this trip."}
     */
    public function destroy(Trip $trip, Place $place): JsonResponse
    {
        $this->authorize('update', $trip);

        try {
            $this->placeService->detachFromTrip($trip, $place);
            return response()->json(['message' => 'Place removed from trip'], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        }
    }

    /**
     * @group Trips / Places
     *
     * Submit or update a vote for a place within a trip.
     *
     * Allows users to rate a place within a trip from 1 to 5.
     *
     * @authenticated
     * @urlParam trip integer required Trip ID. Example: 1
     * @urlParam place integer required Place ID. Example: 5
     * @bodyParam score integer required Rating score (1–5). Example: 4
     * @response 200 scenario="Voted" {"message":"Vote saved"}
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

        return (new TripVoteResource($vote))
            ->additional(['message' => 'Vote saved'])
            ->response();
    }
}
