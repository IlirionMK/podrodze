<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\TripPlaceStoreRequest;
use App\Http\Requests\TripPlaceUpdateRequest;
use App\Http\Resources\TripPlaceResource;
use App\Http\Resources\TripVoteResource;
use App\Interfaces\PlaceInterface;
use App\Models\Trip;
use App\Models\Place;
use Illuminate\Http\JsonResponse;
use DomainException;

class TripPlaceController extends Controller
{
    public function __construct(
        protected PlaceInterface $placeService
    ) {}

    /**
     * @OA\Get(
     *     path="/trips/{trip}/places",
     *     summary="Get all places added to a trip.",
     *     tags={"TripPlaces"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="trip",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer", example=12)
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="List of trip places",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(ref="#/components/schemas/TripPlaceResource")
     *             )
     *         )
     *     )
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
     *     path="/trips/{trip}/places",
     *     summary="Attach a place to a trip or create a custom place.",
     *     description="This endpoint supports:
     *         1) Attaching an existing place using place_id,
     *         2) Creating a custom place with name/category/lat/lon.",
     *     tags={"TripPlaces"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="trip",
     *         in="path",
     *         required=true,
     *         description="Trip ID",
     *         @OA\Schema(type="integer", example=12)
     *     ),
     *
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             oneOf={
     *                 @OA\Schema(
     *                     description="Attach existing place",
     *                     required={"place_id"},
     *                     @OA\Property(property="place_id", type="integer", example=42),
     *                     @OA\Property(property="status", type="string", enum={"proposed","selected","rejected","planned"}),
     *                     @OA\Property(property="is_fixed", type="boolean"),
     *                     @OA\Property(property="day", type="integer", example=1),
     *                     @OA\Property(property="order_index", type="integer"),
     *                     @OA\Property(property="note", type="string", example="Check hours")
     *                 ),
     *                 @OA\Schema(
     *                     description="Create custom place",
     *                     required={"name","category","lat","lon"},
     *                     @OA\Property(property="name", type="string", example="My Custom Point"),
     *                     @OA\Property(property="category", type="string", example="hotel"),
     *                     @OA\Property(property="lat", type="number", example=51.110),
     *                     @OA\Property(property="lon", type="number", example=17.032),
     *                     @OA\Property(property="status", type="string", enum={"proposed","selected","rejected","planned"}),
     *                     @OA\Property(property="is_fixed", type="boolean", example=true),
     *                     @OA\Property(property="day", type="integer", example=1),
     *                     @OA\Property(property="order_index", type="integer"),
     *                     @OA\Property(property="note", type="string", example="Hotel for the group")
     *                 )
     *             }
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=201,
     *         description="Place added to trip",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="data", ref="#/components/schemas/TripPlaceResource")
     *         )
     *     ),
     *
     *     @OA\Response(response=409, description="Conflict: place already attached")
     * )
     */
    public function store(TripPlaceStoreRequest $request, Trip $trip): JsonResponse
    {
        $this->authorize('update', $trip);

        try {
            $dto = $this->placeService->addToTrip($trip, $request->validated(), $request->user());
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }

        return response()->json([
            'message' => 'Place added to trip',
            'data'    => new TripPlaceResource($dto),
        ], 201);
    }

    /**
     * @OA\Patch(
     *     path="/trips/{trip}/places/{place}",
     *     summary="Update metadata for a place in a trip.",
     *     tags={"TripPlaces"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(name="trip", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="place", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", enum={"proposed","selected","rejected","planned"}),
     *             @OA\Property(property="is_fixed", type="boolean"),
     *             @OA\Property(property="day", type="integer"),
     *             @OA\Property(property="order_index", type="integer"),
     *             @OA\Property(property="note", type="string")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Trip place updated",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="data", ref="#/components/schemas/TripPlaceResource")
     *         )
     *     )
     * )
     */
    public function update(TripPlaceUpdateRequest $request, Trip $trip, Place $place): JsonResponse
    {
        $this->authorize('update', $trip);

        try {
            $dto = $this->placeService->updateTripPlace($trip, $place, $request->validated());
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        }

        return response()->json([
            'message' => 'Trip place updated',
            'data'    => new TripPlaceResource($dto),
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/trips/{trip}/places/{place}",
     *     summary="Remove a place from a trip.",
     *     tags={"TripPlaces"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Response(
     *         response=200,
     *         description="Place removed",
     *         @OA\JsonContent(@OA\Property(property="message", type="string"))
     *     )
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
     *     path="/trips/{trip}/places/{place}/vote",
     *     summary="Submit or update a user's vote for a place.",
     *     tags={"TripPlaces"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"score"},
     *             @OA\Property(property="score", type="integer", minimum=1, maximum=5, example=4)
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Vote saved",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="data", ref="#/components/schemas/TripVoteResource")
     *         )
     *     )
     * )
     */
    public function vote(TripPlaceUpdateRequest $request, Trip $trip, Place $place): JsonResponse
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
                (int)$validated['score']
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
