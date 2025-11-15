<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Services\PlacesSyncService;
use App\Interfaces\PlaceInterface;
use App\Http\Resources\PlaceResource;
use DomainException;

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
            'lat'    => ['required', 'numeric', 'between:-90,90'],
            'lon'    => ['required', 'numeric', 'between:-180,180'],
            'radius' => ['nullable', 'integer', 'min:10', 'max:50000'],
        ]);

        $lat = (float) $validated['lat'];
        $lon = (float) $validated['lon'];
        $radius = (int) ($validated['radius'] ?? 2000);

        try {
            // Sync Google Places → DB (cached)
            $summary = $this->placesSync->fetchAndStore($lat, $lon, $radius);

            // Fetch PostGIS nearby places
            $places = $this->placeService->findNearby($lat, $lon, $radius);

        } catch (DomainException $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 400);
        }

        return (PlaceResource::collection($places))
            ->additional([
                'message' => 'Nearby places synchronized successfully',
                'summary' => $summary,
            ])
            ->response();
    }
}
