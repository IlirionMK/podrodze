<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ItineraryResource;
use App\Interfaces\ItineraryServiceInterface;
use App\Models\Trip;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

/**
 * @group Itinerary
 *
 * Manage automatic generation of travel itineraries based on trip data, preferences, and votes.
 */
class ItineraryController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected ItineraryServiceInterface $itineraryService
    ) {}

    /**
     * @group Itinerary
     *
     * Generate a recommended itinerary for a trip (cached for 6 hours).
     *
     * @authenticated
     * @urlParam trip integer required Trip ID. Example: 1
     * @response 200 scenario="Example" {
     *   "data": {
     *     "trip_id": 1,
     *     "day_count": 1,
     *     "schedule": [
     *       {
     *         "day": 1,
     *         "places": [
     *           {
     *             "id": 5,
     *             "name": "Panorama Sky Bar",
     *             "category_slug": "nightlife",
     *             "score": 8.42,
     *             "distance_m": 512
     *           }
     *         ]
     *       }
     *     ],
     *     "cache_info": {
     *       "itinerary_ttl_h": 6,
     *       "places_ttl_h": 12
     *     }
     *   }
     * }
     */
    public function generate(Trip $trip): ItineraryResource
    {
        $this->authorize('view', $trip);
        $dto = $this->itineraryService->generate($trip);
        return new ItineraryResource($dto);
    }

    /**
     * @group Itinerary
     *
     * Build a full itinerary from cached places (clustered by location).
     *
     * @authenticated
     * @urlParam trip integer required Trip ID. Example: 1
     * @response 200 scenario="Example" {
     *   "data": {
     *     "trip_id": 1,
     *     "day_count": 3,
     *     "schedule": [
     *       {"day": 1, "places": [...]},
     *       {"day": 2, "places": [...]}
     *     ]
     *   }
     * }
     * @response 400 scenario="Error" {"message":"No cached places for this trip"}
     */
    public function buildFull(Trip $trip): JsonResponse
    {
        $this->authorize('view', $trip);
        $dto = $this->itineraryService->buildFull($trip);

        if ($dto->day_count === 0) {
            return response()->json(['message' => 'No cached places for this trip'], 400);
        }

        return (new ItineraryResource($dto))
            ->additional(['message' => 'Full itinerary built successfully'])
            ->response();
    }

    /**
     * @group Itinerary
     *
     * Generate a full itinerary route using preferences, votes, and nearby places.
     *
     * @authenticated
     * @urlParam trip integer required Trip ID. Example: 1
     * @bodyParam days integer required Number of days to plan. Example: 3
     * @bodyParam radius integer optional Search radius in meters. Default: 2000
     * @response 200 scenario="Example" {
     *   "data": {
     *     "trip_id": 1,
     *     "day_count": 3,
     *     "schedule": [
     *       {"day": 1, "places": [...]},
     *       {"day": 2, "places": [...]},
     *       {"day": 3, "places": [...]}
     *     ],
     *     "cache_info": {"synced_places": 25}
     *   }
     * }
     */
    public function generateFullRoute(Request $request, Trip $trip): ItineraryResource
    {
        $this->authorize('view', $trip);

        $validated = $request->validate([
            'days' => ['required', 'integer', 'min:1', 'max:30'],
            'radius' => ['nullable', 'integer', 'min:100', 'max:20000'],
        ]);

        $days = (int) $validated['days'];
        $radius = (int) ($validated['radius'] ?? 2000);

        $dto = $this->itineraryService->generateFullRoute($trip, $days, $radius);

        return new ItineraryResource($dto);
    }
}
