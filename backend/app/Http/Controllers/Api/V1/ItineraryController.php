<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ItineraryResource;
use App\Interfaces\ItineraryServiceInterface;
use App\Models\Trip;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use DomainException;
use OpenApi\Attributes as OA;

/**
 * @OA\Tag(
 * name="Itinerary",
 * description="Operations related to itinerary generation and viewing."
 * )
 */
class ItineraryController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected ItineraryServiceInterface $itineraryService
    ) {}

    /**
     * @OA\Get(
     * path="/trips/{trip}/itinerary/generate",
     * summary="Generate a simple one-day recommended itinerary (cached).",
     * tags={"Itinerary"},
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
     * description="Successful operation. Returns generated itinerary data.",
     * @OA\JsonContent(
     * @OA\Property(
     * property="data",
     * ref="#/components/schemas/ItineraryResource"
     * )
     * )
     * ),
     * @OA\Response(response=400, description="Bad Request (e.g., generation failed)."),
     * @OA\Response(response=401, description="Unauthenticated"),
     * @OA\Response(response=403, description="Forbidden (Access denied).")
     * )
     */
    public function generate(Trip $trip): JsonResponse
    {
        $this->authorize('view', $trip);

        try {
            $dto = $this->itineraryService->generate($trip);
        } catch (DomainException $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }

        return (new ItineraryResource($dto))->response();
    }

    /**
     * @OA\Post(
     * path="/trips/{trip}/itinerary/generate-full",
     * summary="Generate a complete multi-day itinerary based on preferences.",
     * tags={"Itinerary"},
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
     * required={"days"},
     * @OA\Property(
     * property="days",
     * type="integer",
     * description="Number of days for the itinerary (1-30).",
     * example=3,
     * minimum=1,
     * maximum=30
     * ),
     * @OA\Property(
     * property="radius",
     * type="integer",
     * nullable=true,
     * description="Search radius in meters (100–20000).",
     * example=2000,
     * minimum=100,
     * maximum=20000
     * )
     * )
     * ),
     * @OA\Response(
     * response=200,
     * description="Itinerary successfully generated and saved.",
     * @OA\JsonContent(
     * @OA\Property(
     * property="data",
     * ref="#/components/schemas/ItineraryResource"
     * )
     * )
     * ),
     * @OA\Response(response=400, description="Bad Request (e.g., generation constraints violated)."),
     * @OA\Response(response=422, description="Validation error."),
     * )
     */
    public function generateFullRoute(Request $request, Trip $trip): JsonResponse
    {
        $this->authorize('view', $trip);

        $validated = $request->validate([
            'days'   => ['required', 'integer', 'min:1', 'max:30'],
            'radius' => ['nullable', 'integer', 'min:100', 'max:20000'],
        ]);

        try {
            $dto = $this->itineraryService->generateFullRoute(
                $trip,
                (int)$validated['days'],
                (int)($validated['radius'] ?? 2000)
            );
        } catch (DomainException $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }

        return (new ItineraryResource($dto))->response();
    }

    /**
     * @OA\Get(
     * path="/trips/{trip}/preferences/aggregate",
     * summary="Aggregate preferences from all members of a trip.",
     * tags={"Itinerary"},
     * security={{"bearerAuth":{}}},
     * @OA\Parameter(
     * name="trip",
     * in="path",
     * required=true,
     * description="The ID of the trip.",
     * @OA\Schema(type="integer", example=4)
     * ),
     * @OA\Response(
     * response=200,
     * description="Successful operation.",
     * @OA\JsonContent(
     * @OA\Property(property="data", type="object", description="Aggregated preference scores."),
     * @OA\Property(property="message", type="string", example="Aggregated preferences calculated.")
     * )
     * ),
     * @OA\Response(response=401, description="Unauthenticated"),
     * @OA\Response(response=403, description="Forbidden (Access denied).")
     * )
     */
    public function aggregatePreferences(Trip $trip): JsonResponse
    {
        $this->authorize('view', $trip);

        $prefs = $this->itineraryService->aggregatePreferences($trip);

        return response()->json([
            'data'    => $prefs,
            'message' => 'Aggregated preferences calculated.',
        ]);
    }
}
