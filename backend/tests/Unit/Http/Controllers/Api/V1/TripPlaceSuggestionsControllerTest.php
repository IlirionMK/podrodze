<?php

namespace Tests\Unit\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\TripPlaceSuggestionsController;
use App\Interfaces\Ai\AiPlaceAdvisorInterface;
use App\Models\Trip;
use App\Models\User;
use App\DTO\Ai\PlaceSuggestionQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\JsonResponse;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;

class TripPlaceSuggestionsControllerTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    private $advisor;
    private $controller;
    private $user;
    private $trip;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->advisor = Mockery::mock(AiPlaceAdvisorInterface::class);
        $this->controller = new TripPlaceSuggestionsController($this->advisor);
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
        $this->trip = Trip::factory()->create(['owner_id' => $this->user->id]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function it_returns_place_suggestions_for_trip()
    {
        $requestData = [
            'limit' => 5,
            'radius_m' => 1000,
            'locale' => 'en'
        ];

        $query = new PlaceSuggestionQuery(
            basedOnPlaceId: null,
            limit: $requestData['limit'],
            radiusMeters: $requestData['radius_m'],
            locale: $requestData['locale']
        );

        $suggestions = new \App\DTO\Ai\SuggestedPlaceCollection(
            items: [
                new \App\DTO\Ai\SuggestedPlace(
                    source: 'test',
                    internalPlaceId: 1,
                    externalId: 'test:1',
                    name: 'Test Place',
                    category: 'test',
                    rating: 4.5,
                    reviewsCount: 100,
                    lat: 52.0,
                    lon: 21.0,
                    distanceMeters: 500,
                    nearPlaceName: 'Nearby Location',
                    estimatedVisitMinutes: 60,
                    score: 0.95,
                    reason: 'Test reason',
                    addPayload: []
                ),
                new \App\DTO\Ai\SuggestedPlace(
                    source: 'test',
                    internalPlaceId: 2,
                    externalId: 'test:2',
                    name: 'Another Place',
                    category: 'test',
                    rating: 4.0,
                    reviewsCount: 50,
                    lat: 52.1,
                    lon: 21.1,
                    distanceMeters: 600,
                    nearPlaceName: 'Another Location',
                    estimatedVisitMinutes: 45,
                    score: 0.85,
                    reason: 'Another reason',
                    addPayload: []
                )
            ],
            meta: ['trip_id' => $this->trip->id]
        );

        $this->advisor
            ->shouldReceive('suggestForTrip')
            ->once()
            ->with(
                $this->trip,
                Mockery::on(function ($arg) use ($query) {
                    return $arg->limit === $query->limit &&
                           $arg->radiusMeters === $query->radiusMeters &&
                           $arg->locale === $query->locale;
                })
            )
            ->andReturn($suggestions);

        // Create and validate the request
        $request = new \App\Http\Requests\Ai\TripPlaceSuggestionsRequest();
        $request->setContainer(app());
        $request->merge($requestData);
        $request->setUserResolver(fn () => $this->user);
        $request->setLaravelSession(app('session.store'));
        
        // Manually validate the request
        $validator = \Illuminate\Support\Facades\Validator::make($requestData, $request->rules());
        $request->setValidator($validator);
        $request->validateResolved();

        $response = $this->controller->__invoke($this->trip, $request);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $responseData = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('data', $responseData);
        $this->assertCount(2, $responseData['data']);
    }

    #[Test]
    public function it_handles_unauthorized_access()
    {
        $otherUser = User::factory()->create();
        $otherTrip = Trip::factory()->create(['owner_id' => $otherUser->id]);

        $this->expectException(\Illuminate\Auth\Access\AuthorizationException::class);
        
        // Create and validate the request
        $request = new \App\Http\Requests\Ai\TripPlaceSuggestionsRequest();
        $request->setContainer(app());
        $request->setUserResolver(fn () => $this->user);
        $request->setLaravelSession(app('session.store'));
        
        // Manually validate the request with empty data
        $validator = \Illuminate\Support\Facades\Validator::make([], $request->rules());
        $request->setValidator($validator);
        $request->validateResolved();
        
        $this->controller->__invoke($otherTrip, $request);
    }
}
