<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Services\PlacesSyncService;
use App\Interfaces\PlaceInterface;
use App\Http\Resources\PlaceResource;
use DomainException;
use Illuminate\Validation\ValidationException;

/**
 * @OA\Tag(
 * name="Places",
 * description="Operations related to place search and data synchronization."
 * )
 */
class PlaceController extends Controller
{
    public function __construct(
        protected PlacesSyncService $placesSync,
        protected PlaceInterface $placeService
    ) {}

    /**
     * @OA\Get(
     * path="/places/nearby",
     * summary="Find nearby places, synchronize them with Google Places API, and filter by distance.",
     * tags={"Places"},
     * security={{"bearerAuth":{}}},
     * @OA\Parameter(
     * name="lat",
     * in="query",
     * required=true,
     * description="Latitude between -90 and 90.",
     * @OA\Schema(type="number", format="float", minimum=-90, maximum=90, example=51.21)
     * ),
     * @OA\Parameter(
     * name="lon",
     * in="query",
     * required=true,
     * description="Longitude between -180 and 180.",
     * @OA\Schema(type="number", format="float", minimum=-180, maximum=180, example=16.16)
     * ),
     * @OA\Parameter(
     * name="radius",
     * in="query",
     * required=false,
     * description="The search radius in meters (10–50000).",
     * @OA\Schema(type="integer", minimum=10, maximum=50000, example=2000)
     * ),
     * @OA\Response(
     * response=200,
     * description="Nearby places synchronized and returned successfully.",
     * @OA\JsonContent(
     * @OA\Property(property="message", type="string", example="Nearby places synchronized successfully"),
     * @OA\Property(
     * property="summary",
     * type="object",
     * description="Summary of database changes.",
     * @OA\Property(property="added", type="integer", example=3),
     * @OA\Property(property="updated", type="integer", example=17)
     * ),
     * @OA\Property(
     * property="data",
     * type="array",
     * description="List of nearby places.",
     * @OA\Items(
     * type="object",
     * @OA\Property(property="id", type="integer", example=1),
     * @OA\Property(property="name", type="string", example="Rynek Główny"),
     * @OA\Property(property="lat", type="number", format="float", example=51.21),
     * @OA\Property(property="lon", type="number", format="float", example=16.16),
     * @OA\Property(property="distance_m", type="number", format="float", example=120.5)
     * )
     * )
     * )
     * ),
     * @OA\Response(
     * response=400,
     * description="Bad Request or Google Places Quota Exceeded.",
     * @OA\JsonContent(
     * @OA\Property(property="error", type="string", example="Google Places API error: OVER_QUERY_LIMIT")
     * )
     * ),
     * @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function nearby(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'lat'    => ['required', 'numeric', 'between:-90,90'],
                'lon'    => ['required', 'numeric', 'between:-180,180'],
                'radius' => ['nullable', 'integer', 'min:10', 'max:50000'],
            ]);
        } catch (ValidationException $e) {
            throw $e;
        }

        $lat = (float) $validated['lat'];
        $lon = (float) $validated['lon'];
        $radius = (int) ($validated['radius'] ?? 2000);

        try {
            $summary = $this->placesSync->fetchAndStore($lat, $lon, $radius);
            $places = $this->placeService->findNearby($lat, $lon, $radius);
        } catch (DomainException $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 400);
        }

        return PlaceResource::collection($places)
            ->additional([
                'message' => 'Nearby places synchronized successfully',
                'summary' => $summary,
            ])
            ->response();
    }
}
