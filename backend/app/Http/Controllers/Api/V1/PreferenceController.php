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
     */
    public function index(Request $request): PreferenceResource
    {
        $dto = $this->preferenceService->getPreferences($request->user());
        return new PreferenceResource($dto);
    }

    /**
     * @group Preferences
     *
     * Update user's preferences.
     *
     * @authenticated
     */
    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'preferences' => ['required', 'array'],
        ]);

        $result = $this->preferenceService->updatePreferences(
            $request->user(),
            $data['preferences']
        );

        return response()->json($result, 200);
    }
}
