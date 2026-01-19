<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTripRequest;
use App\Http\Requests\UpdateTripRequest;
use App\Http\Resources\TripResource;
use App\Interfaces\TripInterface;
use App\Models\Trip;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use DomainException;

/**
 * @OA\Tag(
 * name="Trips",
 * description="CRUD operations and settings for user trips."
 * )
 */
class TripController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected TripInterface $tripService
    ) {}

    /**
     * @OA\Get(
     * path="/trips",
     * summary="List trips accessible for the current user.",
     * tags={"Trips"},
     * security={{"bearerAuth":{}}},
     * @OA\Response(
     * response=200,
     * description="Successful operation.",
     * @OA\JsonContent(
     * @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/TripResource"))
     * )
     * ),
     * @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $trips = $this->tripService->list($request->user());
        return TripResource::collection($trips)->response();
    }

    /**
     * @OA\Post(
     * path="/trips",
     * summary="Create a new trip.",
     * tags={"Trips"},
     * security={{"bearerAuth":{}}},
     * @OA\RequestBody(
     * required=true,
     * @OA\JsonContent(
     * required={"name"},
     * @OA\Property(property="name", type="string", description="Trip name.", example="Weekend in Wrocław"),
     * @OA\Property(property="start_date", type="string", format="date", nullable=true, example="2025-11-29"),
     * @OA\Property(property="end_date", type="string", format="date", nullable=true, example="2025-12-02")
     * )
     * ),
     * @OA\Response(
     * response=201,
     * description="Trip created successfully.",
     * @OA\JsonContent(
     * @OA\Property(property="message", type="string", example="Trip created successfully"),
     * @OA\Property(property="data", ref="#/components/schemas/TripResource")
     * )
     * ),
     * @OA\Response(response=422, description="Validation error."),
     * @OA\Response(response=401, description="Unauthenticated")
     * )
     *
     * @throws DomainException
     */
    public function store(StoreTripRequest $request): JsonResponse
    {
        $trip = $this->tripService->create($request->validated(), $request->user());

        return response()->json([
            'message' => 'Trip created successfully',
            'data'    => new TripResource($trip),
        ], 201);
    }

    /**
     * @OA\Get(
     * path="/trips/{trip}",
     * summary="Get a specific trip by ID.",
     * tags={"Trips"},
     * security={{"bearerAuth":{}}},
     * @OA\Parameter(
     * name="trip",
     * in="path",
     * required=true,
     * description="The ID of the trip.",
     * @OA\Schema(type="integer", example=10)
     * ),
     * @OA\Response(
     * response=200,
     * description="Successful operation.",
     * @OA\JsonContent(
     * @OA\Property(property="data", ref="#/components/schemas/TripResource")
     * )
     * ),
     * @OA\Response(response=401, description="Unauthenticated"),
     * @OA\Response(response=403, description="Forbidden"),
     * @OA\Response(response=404, description="Trip not found.")
     * )
     */
    public function show(Trip $trip): JsonResponse
    {
        $this->authorize('view', $trip);

        $trip->load(['owner', 'members']);

        return response()->json([
            'data' => new TripResource($trip),
        ]);
    }

    /**
     * @OA\Put(
     * path="/trips/{trip}",
     * summary="Update an existing trip.",
     * tags={"Trips"},
     * security={{"bearerAuth":{}}},
     * @OA\Parameter(
     * name="trip",
     * in="path",
     * required=true,
     * description="The ID of the trip.",
     * @OA\Schema(type="integer", example=10)
     * ),
     * @OA\RequestBody(
     * required=true,
     * @OA\JsonContent(
     * @OA\Property(property="name", type="string", description="New trip name.", example="Updated trip name"),
     * @OA\Property(property="start_date", type="string", format="date", nullable=true, example="2025-11-30"),
     * @OA\Property(property="end_date", type="string", format="date", nullable=true, example="2025-12-21")
     * )
     * ),
     * @OA\Response(
     * response=200,
     * description="Trip updated successfully.",
     * @OA\JsonContent(
     * @OA\Property(property="message", type="string", example="Trip updated successfully"),
     * @OA\Property(property="data", ref="#/components/schemas/TripResource")
     * )
     * ),
     * @OA\Response(response=400, description="Bad Request (Domain error)."),
     * @OA\Response(response=422, description="Validation error.")
     * )
     *
     * @throws DomainException
     */
    public function update(UpdateTripRequest $request, Trip $trip): JsonResponse
    {
        $this->authorize('update', $trip);

        try {
            $updated = $this->tripService->update($request->validated(), $trip);
        } catch (DomainException $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }

        return response()->json([
            'message' => 'Trip updated successfully',
            'data'    => new TripResource($updated),
        ]);
    }

    /**
     * @OA\Delete(
     * path="/trips/{trip}",
     * summary="Delete a trip.",
     * tags={"Trips"},
     * security={{"bearerAuth":{}}},
     * @OA\Parameter(
     * name="trip",
     * in="path",
     * required=true,
     * description="The ID of the trip.",
     * @OA\Schema(type="integer", example=10)
     * ),
     * @OA\Response(
     * response=200,
     * description="Trip successfully deleted.",
     * @OA\JsonContent(
     * @OA\Property(property="message", type="string", example="Trip deleted successfully")
     * )
     * ),
     * @OA\Response(response=401, description="Unauthenticated"),
     * @OA\Response(response=403, description="Forbidden (Access denied).")
     * )
     */
    public function destroy(Trip $trip): JsonResponse
    {
        $this->authorize('delete', $trip);

        $this->tripService->delete($trip);

        return response()->json([
            'message' => 'Trip deleted successfully'
        ]);
    }

    /**
     * @OA\Patch(
     * path="/trips/{trip}/start-location",
     * summary="Update the starting location (latitude and longitude) for a trip.",
     * tags={"Trips"},
     * security={{"bearerAuth":{}}},
     * @OA\Parameter(
     * name="trip",
     * in="path",
     * required=true,
     * description="The ID of the trip.",
     * @OA\Schema(type="integer", example=10)
     * ),
     * @OA\RequestBody(
     * required=true,
     * @OA\JsonContent(
     * required={"start_latitude", "start_longitude"},
     * @OA\Property(property="start_latitude", type="number", format="float", minimum=-90, maximum=90, example=51.21),
     * @OA\Property(property="start_longitude", type="number", format="float", minimum=-180, maximum=180, example=16.16)
     * )
     * ),
     * @OA\Response(
     * response=200,
     * description="Start location updated successfully.",
     * @OA\JsonContent(
     * @OA\Property(property="message", type="string", example="Start location updated"),
     * @OA\Property(property="data", ref="#/components/schemas/TripResource")
     * )
     * ),
     * @OA\Response(response=400, description="Bad Request (Domain error)."),
     * @OA\Response(response=422, description="Validation error.")
     * )
     *
     * @throws DomainException
     */
    public function updateStartLocation(Request $request, Trip $trip): JsonResponse
    {
        $this->authorize('update', $trip);

        $validated = $request->validate([
            'start_latitude'  => ['required', 'numeric', 'between:-90,90'],
            'start_longitude' => ['required', 'numeric', 'between:-180,180'],
        ]);

        try {
            $updated = $this->tripService->updateStartLocation($validated, $trip);
        } catch (DomainException $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }

        return response()->json([
            'message' => 'Start location updated',
            'data'    => new TripResource($updated),
        ]);
    }

    public function leave(Request $request, Trip $trip)
{
    $user = $request->user();

    // sprawdzamy, czy użytkownik jest w podróży
    if (!$trip->users()->where('user_id', $user->id)->exists()) {
        return response()->json(['message' => 'Nie należysz do tej podróży'], 403);
    }

    // usuwa użytkownika z podróży
    $trip->users()->detach($user->id);

    return response()->json(['message' => 'Opusciłeś podróż']);
}

public function current(Request $request)
{
    $user = $request->user();

    $trip = Trip::whereHas('users', function ($q) use ($user) {
        $q->where('users.id', $user->id);
    })
    ->latest()
    ->first();

    return response()->json($trip);
}

}
