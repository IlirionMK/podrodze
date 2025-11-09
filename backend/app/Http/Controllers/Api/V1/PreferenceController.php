<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\PreferenceResource;
use App\Interfaces\PreferenceServiceInterface;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class PreferenceController extends Controller
{
    public function __construct(
        protected PreferenceServiceInterface $preferenceService
    ) {}

    /**
     * @group Preferences
     *
     * Get available categories and user's current preferences.
     *
     * @authenticated
     * @response 200 scenario="Example" {
     *   "data": {
     *     "categories": [
     *       {"slug": "museum", "name": "Muzea"},
     *       {"slug": "food", "name": "Jedzenie"}
     *     ],
     *     "user": {
     *       "museum": 2,
     *       "food": 1,
     *       "nature": 0
     *     }
     *   }
     * }
     */
    public function index(Request $request): PreferenceResource
    {
        $dto = $this->preferenceService->getPreferences($request);
        return new PreferenceResource($dto);
    }

    /**
     * @group Preferences
     *
     * Update user's preferences.
     *
     * @authenticated
     * @bodyParam preferences object required Example: {"museum":2,"food":1,"nature":0}
     * @response 200 scenario="Success" {"status": "ok"}
     */
    public function update(Request $request): JsonResponse
    {
        $result = $this->preferenceService->updatePreferences($request);
        return response()->json($result, 200);
    }
}
