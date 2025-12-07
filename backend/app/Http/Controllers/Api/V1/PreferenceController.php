<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\PreferenceResource;
use App\Interfaces\PreferenceServiceInterface;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use DomainException;

/**
 * @OA\Tag(
 * name="Preferences",
 * description="Operations related to user preferences and settings."
 * )
 */
class PreferenceController extends Controller
{
    public function __construct(
        protected PreferenceServiceInterface $preferenceService
    ) {}

    /**
     * @OA\Get(
     * path="/preferences",
     * summary="Get all categories and the user's current preference values.",
     * tags={"Preferences"},
     * security={{"bearerAuth":{}}},
     * @OA\Response(
     * response=200,
     * description="Successful operation.",
     * @OA\JsonContent(
     * @OA\Property(
     * property="data",
     * type="object",
     * @OA\Property(
     * property="categories",
     * type="array",
     * description="List of available categories.",
     * @OA\Items(type="string", example="museum")
     * ),
     * @OA\Property(
     * property="user",
     * type="object",
     * description="User's current preference scores (0=low, 2=high).",
     * @OA\Property(property="museum", type="integer", example=2),
     * @OA\Property(property="food", type="integer", example=1),
     * @OA\Property(property="nature", type="integer", example=0),
     * @OA\Property(property="nightlife", type="integer", example=2)
     * )
     * )
     * )
     * ),
     * @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $dto = $this->preferenceService->getPreferences($request->user());
        return (new PreferenceResource($dto))->response();
    }

    /**
     * @OA\Put(
     * path="/users/me/preferences",
     * summary="Update user's preferences.",
     * tags={"Preferences"},
     * security={{"bearerAuth":{}}},
     * @OA\RequestBody(
     * required=true,
     * description="User preference scores for each category.",
     * @OA\JsonContent(
     * required={"preferences"},
     * @OA\Property(
     * property="preferences",
     * type="object",
     * @OA\Property(property="museum", type="integer", description="Score (0-2).", example=2, minimum=0, maximum=2),
     * @OA\Property(property="food", type="integer", description="Score (0-2).", example=1, minimum=0, maximum=2),
     * @OA\Property(property="nature", type="integer", description="Score (0-2).", example=0, minimum=0, maximum=2),
     * @OA\Property(property="nightlife", type="integer", description="Score (0-2).", example=1, minimum=0, maximum=2)
     * )
     * )
     * ),
     * @OA\Response(
     * response=200,
     * description="Preferences successfully updated.",
     * @OA\JsonContent(
     * @OA\Property(property="message", type="string", example="Preferences updated"),
     * @OA\Property(property="data", type="object", description="Updated preferences data.")
     * )
     * ),
     * @OA\Response(
     * response=400,
     * description="Invalid preferences payload or Domain error.",
     * @OA\JsonContent(
     * @OA\Property(property="error", type="string", example="Invalid preferences payload")
     * )
     * ),
     * @OA\Response(response=422, description="Validation error.")
     * )
     *
     * @throws \DomainException
     */
    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'preferences' => ['required', 'array'],

            'preferences.museum'    => ['required', 'integer', 'between:0,2'],
            'preferences.food'      => ['required', 'integer', 'between:0,2'],
            'preferences.nature'    => ['required', 'integer', 'between:0,2'],
            'preferences.nightlife' => ['required', 'integer', 'between:0,2'],
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
            'data'    => (new PreferenceResource($dto))->resolve(),
        ]);
    }
}
