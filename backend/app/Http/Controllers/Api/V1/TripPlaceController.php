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

/**
 * @OA\Tag(
 * name="TripPlaces",
 * description="Operations for managing places attached to a specific trip."
 * )
 */
class TripPlaceController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected PlaceInterface $placeService
    ) {}

    /**
     * @OA\Get(
     * path="/trips/{trip}/places",
     * summary="Get all places attached to a trip.",
     * tags={"TripPlaces"},
     * security={{"bearerAuth":{}}},
     * @OA\Parameter(
     * name="trip",
     * in="path",
     * required=true,
     * description="The ID of the trip.",
     * @OA\Schema(type="integer", example=12)
     * ),
     * @OA\Response(
     * response=200,
     * description="Successful operation.",
     * @OA\JsonContent(
     * @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/TripPlaceResource"))
     * )
     * ),
     * @OA\Response(response=401, description="Unauthenticated"),
     * @OA\Response(response=403, description="Forbidden (Access denied).")
     * )
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
     * @OA\Post(
     * path="/trips/{trip}/places",
     * summary="Attach a place to a trip.",
     * tags={"TripPlaces"},
     * security={{"bearerAuth":{}}},
     * @OA\Parameter(
     * name="trip",
     * in="path",
     * required=true,
     * description="The ID of the trip.",
     * @OA\Schema(type="integer", example=12)
     * ),
     * @OA\RequestBody(
     * required=true,
     * @OA\JsonContent(
     * required={"place_id"},
     * @OA\Property(property="place_id", type="integer", description="The ID of the place to attach.", example=237),
     * @OA\Property(property="status", type="string", description="Status of the place in the trip.", enum={"proposed", "selected", "rejected", "planned"}, example="proposed"),
     * @OA\Property(property="is_fixed", type="boolean", description="Whether the place's schedule is fixed.", example=false),
     * @OA\Property(property="day", type="integer", description="The day of the trip the place is planned for.", example=1, minimum=1),
     * @OA\Property(property="order_index", type="integer", description="Order index within the day.", example=0, minimum=0),
     * @OA\Property(property="note", type="string", description="Optional note about the place.", example="Must visit", maxLength=255),
     * )
     * ),
     * @OA\Response(
     * response=201,
     * description="Place added to trip successfully.",
     * @OA\JsonContent(
     * @OA\Property(property="message", type="string", example="Place added to trip"),
     * @OA\Property(property="data", ref="#/components/schemas/TripPlaceResource")
     * )
     * ),
     * @OA\Response(response=409, description="Conflict (Place already attached to trip)."),
     * @OA\Response(response=422, description="Validation error.")
     * )
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
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 409); // 409 Conflict for existing resource
        }

        return response()->json([
            'message' => 'Place added to trip',
            'data'    => new TripPlaceResource($dto),
        ], 201);
    }

    /**
     * @OA\Patch(
     * path="/trips/{trip}/places/{place}",
     * summary="Update pivot data (status, day, order) for a place in a trip.",
     * tags={"TripPlaces"},
     * security={{"bearerAuth":{}}},
     * @OA\Parameter(
     * name="trip",
     * in="path",
     * required=true,
     * description="The ID of the trip.",
     * @OA\Schema(type="integer", example=12)
     * ),
     * @OA\Parameter(
     * name="place",
     * in="path",
     * required=true,
     * description="The ID of the place.",
     * @OA\Schema(type="integer", example=237)
     * ),
     * @OA\RequestBody(
     * @OA\JsonContent(
     * @OA\Property(property="status", type="string", description="New status.", enum={"proposed", "selected", "rejected", "planned"}, example="planned"),
     * @OA\Property(property="is_fixed", type="boolean", description="Whether the schedule is fixed.", example=true),
     * @OA\Property(property="day", type="integer", description="The new day of the trip.", example=1),
     * @OA\Property(property="order_index", type="integer", description="New order index within the day.", example=0),
     * @OA\Property(property="note", type="string", description="Updated note.", example="Checking times"),
     * )
     * ),
     * @OA\Response(
     * response=200,
     * description="Trip place updated successfully.",
     * @OA\JsonContent(
     * @OA\Property(property="message", type="string", example="Trip place updated"),
     * @OA\Property(property="data", ref="#/components/schemas/TripPlaceResource")
     * )
     * ),
     * @OA\Response(response=404, description="Not Found (Place is not attached to this trip)."),
     * @OA\Response(response=422, description="Validation error.")
     * )
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
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 404); // 404 Not Found if pivot doesn't exist
        }

        return response()->json([
            'message' => 'Trip place updated',
            'data'    => new TripPlaceResource($dto),
        ]);
    }

    /**
     * @OA\Delete(
     * path="/trips/{trip}/places/{place}",
     * summary="Remove a place from a trip.",
     * tags={"TripPlaces"},
     * security={{"bearerAuth":{}}},
     * @OA\Parameter(
     * name="trip",
     * in="path",
     * required=true,
     * description="The ID of the trip.",
     * @OA\Schema(type="integer", example=12)
     * ),
     * @OA\Parameter(
     * name="place",
     * in="path",
     * required=true,
     * description="The ID of the place.",
     * @OA\Schema(type="integer", example=237)
     * ),
     * @OA\Response(
     * response=200,
     * description="Place removed successfully.",
     * @OA\JsonContent(
     * @OA\Property(property="message", type="string", example="Place removed from trip")
     * )
     * ),
     * @OA\Response(response=404, description="Not Found (Place is not attached to this trip).")
     * )
     */
    public function destroy(Trip $trip, Place $place): JsonResponse
    {
        $this->authorize('update', $trip);

        try {
            $this->placeService->detachFromTrip($trip, $place);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        }

        return response()->json([
            'message' => 'Place removed from trip',
        ]);
    }

    /**
     * @OA\Post(
     * path="/trips/{trip}/places/{place}/vote",
     * summary="Submit or update a user's vote for a place in a trip.",
     * tags={"TripPlaces"},
     * security={{"bearerAuth":{}}},
     * @OA\Parameter(
     * name="trip",
     * in="path",
     * required=true,
     * description="The ID of the trip.",
     * @OA\Schema(type="integer", example=12)
     * ),
     * @OA\Parameter(
     * name="place",
     * in="path",
     * required=true,
     * description="The ID of the place.",
     * @OA\Schema(type="integer", example=237)
     * ),
     * @OA\RequestBody(
     * required=true,
     * @OA\JsonContent(
     * required={"score"},
     * @OA\Property(property="score", type="integer", description="Vote score (1 to 5).", example=4, minimum=1, maximum=5)
     * )
     * ),
     * @OA\Response(
     * response=200,
     * description="Vote saved and aggregate score returned.",
     * @OA\JsonContent(
     * @OA\Property(property="message", type="string", example="Vote saved"),
     * @OA\Property(property="data", ref="#/components/schemas/TripVoteResource")
     * )
     * ),
     * @OA\Response(response=400, description="Bad Request (Domain error or validation)."),
     * @OA\Response(response=422, description="Validation error.")
     * )
     */
    public function vote(Request $request, Trip $trip, Place $place): JsonResponse
    {
        $this->authorize('view', $trip);

        $validated = $request->validate([
            'score' => ['required', 'integer', 'min:1', 'max:5'],
        ]);

        try {
            $vote = $this->placeService->saveTripVote(
                $trip,
                $place,
                $request->user(),
                (int) $validated['score']
            );
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }

        return response()->json([
            'message' => 'Vote saved',
            'data'    => new TripVoteResource($vote),
        ]);
    }
}
