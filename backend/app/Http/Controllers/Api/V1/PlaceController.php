<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\PlaceResource;
use App\Interfaces\PlaceInterface;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PlaceController extends Controller
{
    public function __construct(
        protected PlaceInterface $placeService
    ) {}

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
            $result = $this->placeService->nearbyWithSync($lat, $lon, $radius);
        } catch (DomainException $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }

        return PlaceResource::collection($result['places'])
            ->additional([
                'message' => 'Nearby places synchronized successfully',
                'summary' => $result['summary'],
            ])
            ->response();
    }

    public function autocomplete(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q'        => ['required', 'string', 'min:2', 'max:255'],
            'lat'      => ['nullable', 'numeric', 'between:-90,90'],
            'lon'      => ['nullable', 'numeric', 'between:-180,180'],
            'radius'   => ['nullable', 'integer', 'min:10', 'max:50000'],
            'language' => ['nullable', 'string', 'max:10'],
        ]);

        $items = $this->placeService->googleAutocomplete(
            query: (string) $validated['q'],
            lat: isset($validated['lat']) ? (float) $validated['lat'] : null,
            lon: isset($validated['lon']) ? (float) $validated['lon'] : null,
            radius: isset($validated['radius']) ? (int) $validated['radius'] : null,
            language: (string) ($validated['language'] ?? 'pl'),
            sessionToken: null
        );

        return response()->json(['data' => $items]);
    }

    public function googleDetails(Request $request, string $googlePlaceId): JsonResponse
    {
        $validated = $request->validate([
            'language' => ['nullable', 'string', 'max:10'],
        ]);

        $details = $this->placeService->googleDetails(
            googlePlaceId: $googlePlaceId,
            language: (string) ($validated['language'] ?? 'pl'),
            sessionToken: null
        );

        if (!$details) {
            return response()->json(['message' => 'Place not found'], 404);
        }

        return response()->json(['data' => $details]);
    }
}
