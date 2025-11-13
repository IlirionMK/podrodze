<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Services\PlacesSyncService;
use App\Interfaces\PlaceInterface;
use App\Http\Resources\PlaceResource;

class PlaceController extends Controller
{
    public function __construct(
        protected PlacesSyncService $placesSync,
        protected PlaceInterface $placeService
    ) {}

    /**
     * @group Places
     *
     * Find nearby places using Google sync + PostGIS distance.
     */
    public function nearby(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lon' => ['required', 'numeric', 'between:-180,180'],
            'radius' => ['nullable', 'integer', 'min:10', 'max:50000'],
        ]);

        $lat = (float) $validated['lat'];
        $lon = (float) $validated['lon'];
        $radius = (int) ($validated['radius'] ?? 2000);

        // Sync Google places into DB (cached requests)
        $summary = $this->placesSync->fetchAndStore($lat, $lon, $radius);

        // Fetch nearby places using PostGIS via service
        $places = $this->placeService->findNearby($lat, $lon, $radius);

        return response()->json([
            'message' => 'Nearby places synchronized successfully.',
            'summary' => $summary,
            'data'    => PlaceResource::collection($places),
        ]);
    }
}
