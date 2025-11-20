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
     * @group TripPlaces
     * @authenticated
     * @operationId listTripPlaces
     *
     * Get all places attached to a trip.
     *
     * @urlParam trip_id integer required ID of the trip. Example: 12
     *
     * @response 200 {
     *   "data": [...]
     * }
     */
    public function index(Trip $trip_id): JsonResponse
    {
        $this->authorize('view', $trip_id);

        $places = $this->placeService->listForTrip($trip_id);

        return response()->json([
            'data' => TripPlaceResource::collection($places)->resolve()
        ]);
    }

    /**
     * @group TripPlaces
     * @authenticated
     * @operationId attachPlaceToTrip
     *
     * Attach a place to the trip.
     *
     * @urlParam trip_id integer required ID of the trip. Example: 12
     *
     * @bodyParam place_id integer required ID of the place. Example: 237
     * @bodyParam status string Status of selection. Example: proposed
     * @bodyParam is_fixed boolean Whether this place is fixed.
     * @bodyParam day integer Day number.
     * @bodyParam order_index integer Order index.
     * @bodyParam note string Optional note.
     *
     * @response 201 {
     *   "message": "Place added to trip",
     *   "data": {...}
     * }
     *
     * @response 404 { "message": "Place not found" }
     * @response 409 { "message": "Place already attached or invalid state" }
     */
    public function store(Request $request, Trip $trip_id): JsonResponse
    {
        $this->authorize('update', $trip_id);

        $validated = $request->validate([
            'place_id'    => 'required|exists:places,id',
            'status'      => 'nullable|string|in:proposed,selected,rejected,planned',
            'is_fixed'    => 'nullable|boolean',
            'day'         => 'nullable|integer|min:1',
            'order_index' => 'nullable|integer|min:0',
            'note'        => 'nullable|string|max:255',
        ]);

        try {
            $dto = $this->placeService->attachToTrip($trip_id, $validated, $request->user());

            return response()->json([
                'message' => 'Place added to trip',
                'data' => (new TripPlaceResource($dto))->resolve()
            ], 201);

        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 409);

        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        }
    }

    /**
     * @group TripPlaces
     * @authenticated
     * @operationId updateTripPlace
     *
     * Update a place entry belonging to the trip.
     *
     * @urlParam trip_id integer required ID of the trip. Example: 12
     * @urlParam place_id integer required ID of the place. Example: 237
     *
     * @response 200 { "message": "Trip place updated", "data": {...} }
     * @response 404 { "message": "Place not found" }
     */
    public function update(Request $request, Trip $trip_id, Place $place_id): JsonResponse
    {
        $this->authorize('update', $trip_id);

        $validated = $request->validate([
            'status'      => 'nullable|string|in:proposed,selected,rejected,planned',
            'is_fixed'    => 'nullable|boolean',
            'day'         => 'nullable|integer|min:1',
            'order_index' => 'nullable|integer|min:0',
            'note'        => 'nullable|string|max:255',
        ]);

        try {
            $dto = $this->placeService->updateTripPlace($trip_id, $place_id, $validated);

            return response()->json([
                'message' => 'Trip place updated',
                'data' => (new TripPlaceResource($dto))->resolve()
            ]);

        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        }
    }

    /**
     * @group TripPlaces
     * @authenticated
     * @operationId removePlaceFromTrip
     *
     * Remove a place from the trip.
     *
     * @urlParam trip_id integer required
     * @urlParam place_id integer required
     *
     * @response 200 { "message": "Place removed from trip" }
     * @response 404 { "message": "Place not found" }
     */
    public function destroy(Trip $trip_id, Place $place_id): JsonResponse
    {
        $this->authorize('update', $trip_id);

        try {
            $this->placeService->detachFromTrip($trip_id, $place_id);

            return response()->json(['message' => 'Place removed from trip']);

        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        }
    }

    /**
     * @group TripPlaces
     * @authenticated
     * @operationId voteForTripPlace
     *
     * Submit or update a vote for a place within the trip.
     *
     * @urlParam trip_id integer required Example: 12
     * @urlParam place_id integer required Example: 237
     *
     * @bodyParam score integer required Must be between 1 and 5. Example: 4
     *
     * @response 200 { "message": "Vote saved", "data": {...} }
     * @response 400 { "message": "Invalid vote" }
     */
    public function vote(Request $request, Trip $trip_id, Place $place_id): JsonResponse
    {
        $this->authorize('view', $trip_id);

        $validated = $request->validate([
            'score' => ['required', 'integer', 'min:1', 'max:5'],
        ]);

        try {
            $vote = $this->placeService->saveTripVote(
                $trip_id,
                $place_id,
                $request->user(),
                (int) $validated['score']
            );

        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }

        return response()->json([
            'message' => 'Vote saved',
            'data' => (new TripVoteResource($vote))->resolve()
        ]);
    }
}
