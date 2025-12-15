<?php

namespace App\Http\Controllers\Api\V1;

use App\DTO\Ai\PlaceSuggestionQuery;
use App\Http\Controllers\Controller;
use App\Http\Requests\Ai\TripPlaceSuggestionsRequest;
use App\Http\Resources\SuggestedPlaceResource;
use App\Interfaces\Ai\AiPlaceAdvisorInterface;
use App\Models\Trip;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;

final class TripPlaceSuggestionsController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private readonly AiPlaceAdvisorInterface $advisor
    ) {}

    public function __invoke(Trip $trip, TripPlaceSuggestionsRequest $request): JsonResponse
    {
        $this->authorize('view', $trip);

        $query = PlaceSuggestionQuery::fromArray($request->validated());

        $result = $this->advisor->suggestForTrip($trip, $query);

        return response()->json([
            'data' => SuggestedPlaceResource::collection($result->items),
            'meta' => $result->meta,
        ]);
    }
}
