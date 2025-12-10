<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\PreferenceResource;
use App\Interfaces\PreferenceServiceInterface;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use DomainException;

/**
 * @OA\Tag(
 *     name="Preferences",
 *     description="Operations related to user preferences and settings."
 * )
 */
class PreferenceController extends Controller
{
    public function __construct(
        protected PreferenceServiceInterface $preferenceService
    ) {}

    /**
     * @OA\Get(
     *     path="/preferences",
     *     summary="Get user preference categories and current scores.",
     *     tags={"Preferences"},
     *     security={{"bearerAuth":{}}}
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $dto = $this->preferenceService->getPreferences($request->user());

        return (new PreferenceResource($dto))->response();
    }

    /**
     * @OA\Put(
     *     path="/users/me/preferences",
     *     summary="Update user's preference scores (0–2).",
     *     tags={"Preferences"},
     *     security={{"bearerAuth":{}}}
     * )
     */
    public function update(Request $request): JsonResponse
    {
        // Categories that participate in user preferences
        $allowed = Category::where('include_in_preferences', true)
            ->pluck('slug')
            ->toArray();

        if (empty($allowed)) {
            return response()->json([
                'error' => 'No preference categories configured.'
            ], 400);
        }

        // Build dynamic validation rules
        $rules = [
            'preferences' => ['required', 'array'],
        ];

        foreach ($allowed as $slug) {
            $rules["preferences.$slug"] = ['required', 'integer', 'between:0,2'];
        }

        $validated = $request->validate($rules);

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
            'data'    => (new PreferenceResource($dto))->resolve(),
        ]);
    }
}
