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
            'data' => new PreferenceResource($dto),
            'message' => 'Preferences updated',
        ]);
    }
}
