<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\PreferenceResource;
use App\Interfaces\PreferenceServiceInterface;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use DomainException;

class PreferenceController extends Controller
{
    public function __construct(
        protected PreferenceServiceInterface $preferenceService
    ) {}

    /**
     * @group Preferences
     * @authenticated
     * @operationId getUserPreferences
     *
     * Get all categories and the user's current preference values.
     *
     * @response 200 {
     *   "data": {
     *     "categories": [
     *       { "slug": "museum", "name": "Muzeum" },
     *       { "slug": "food", "name": "Jedzenie" }
     *     ],
     *     "user": {
     *       "museum": 2,
     *       "food": 1,
     *       "nature": 0,
     *       "nightlife": 2
     *     }
     *   }
     * }
     */
    public function index(Request $request): PreferenceResource
    {
        $dto = $this->preferenceService->getPreferences($request->user());
        return new PreferenceResource($dto);
    }

    /**
     * @group Preferences
     * @authenticated
     * @operationId updateUserPreferences
     *
     * Update user's preferences.
     *
     * @bodyParam preferences object required Key-value pairs of category -> score.
     * @bodyParam preferences.museum int Preference for museums (0–2). Example: 2
     * @bodyParam preferences.food int Preference for food (0–2). Example: 1
     * @bodyParam preferences.nature int Preference for nature (0–2). Example: 0
     * @bodyParam preferences.nightlife int Preference for nightlife (0–2). Example: 1
     *
     * @response 200 {
     *   "message": "Preferences updated",
     *   "data": {
     *     "categories": [
     *       { "slug": "museum", "name": "Muzeum" },
     *       { "slug": "food", "name": "Jedzenie" }
     *     ],
     *     "user": {
     *       "museum": 2,
     *       "food": 1,
     *       "nature": 0,
     *       "nightlife": 1
     *     }
     *   }
     * }
     *
     * @response 400 {
     *   "error": "Invalid preferences payload"
     * }
     */
    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'preferences' => ['required', 'array'],
        ]);

        try {
            $dto = $this->preferenceService->updatePreferences(
                $request->user(),
                $validated['preferences']
            );
        } catch (DomainException $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 400);
        }

        return response()->json([
            'message' => 'Preferences updated',
            'data' => (new PreferenceResource($dto))->resolve(),
        ]);
    }
}
