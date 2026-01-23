<?php

namespace Tests\Unit\Services\Ai;

use App\DTO\Ai\PlaceSuggestionQuery;
use App\DTO\Ai\SuggestedPlace;
use App\DTO\Ai\SuggestedPlaceCollection;
use App\Interfaces\Ai\AiPlaceReasonerInterface;
use App\Interfaces\Ai\PlacesCandidateProviderInterface;
use App\Interfaces\PreferenceAggregatorServiceInterface;
use App\Models\Place;
use App\Models\Trip;
use App\Models\User;
use App\Services\Ai\AiPlaceAdvisorService;
use App\Services\Ai\CategoryNormalizer;
use App\Services\Ai\GeminiEnhancerService;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AiPlaceAdvisorServiceTest extends TestCase
{
    private AiPlaceAdvisorService $service;
    private MockInterface $preferences;
    private MockInterface $candidateProvider;
    private MockInterface $reasoner;
    private CategoryNormalizer|MockInterface $categories;
    private MockInterface $aiEnhancer;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // Create mocks for all dependencies
        $this->preferences = Mockery::mock(PreferenceAggregatorServiceInterface::class);
        $this->candidateProvider = Mockery::mock(PlacesCandidateProviderInterface::class);
        $this->reasoner = Mockery::mock(AiPlaceReasonerInterface::class);

        // Use the real CategoryNormalizer instance since it's a final class
        $this->categories = new CategoryNormalizer();

        $this->aiEnhancer = Mockery::mock(GeminiEnhancerService::class);
        
        // Create a test user
        $this->user = User::factory()->create();

        // Create the service with mocked dependencies
        $this->service = new AiPlaceAdvisorService(
            $this->preferences,
            $this->candidateProvider,
            $this->reasoner,
            $this->categories,
            $this->aiEnhancer
        );

        // Configure the test environment to work with the real CategoryNormalizer
        // We'll use the default behavior of isRecommendable() which checks against RECOMMENDABLE categories
    }

    #[Test]
    public function it_returns_empty_collection_when_suggestions_are_disabled()
    {
        // Disable suggestions in config
        config(['ai.suggestions.enabled' => false]);

        $trip = Trip::factory()->create([
            'owner_id' => $this->user->id,
            'start_latitude' => 52.2297,
            'start_longitude' => 21.0122,
        ]);
        $query = new PlaceSuggestionQuery(
            basedOnPlaceId: null,
            limit: 10,
            radiusMeters: 1000,
            locale: 'en'
        );

        $result = $this->service->suggestForTrip($trip, $query);

        $this->assertInstanceOf(SuggestedPlaceCollection::class, $result);
        $this->assertEmpty($result->items);
        $this->assertEquals($trip->id, $result->meta['trip_id']);
    }

    #[Test]
    public function it_returns_cached_results_when_available()
    {
        // Enable suggestions
        config(['ai.suggestions.enabled' => true]);

        $trip = Trip::factory()->create(['owner_id' => $this->user->id]);
        $query = new PlaceSuggestionQuery(
            basedOnPlaceId: null,
            limit: 10,
            radiusMeters: 1000,
            locale: 'en'
        );

        // Mock preferences
        $this->preferences->shouldReceive('getGroupPreferences')
            ->once()
            ->with($trip)
            ->andReturn(['food' => 1.0]);

        // Mock cache to return a value
        $cachedResult = [
            'items' => [
                [
                    'name' => 'Cached Place',
                    'category' => 'restaurant',
                    'source' => 'cache',
                    'internal_place_id' => 1,
                    'rating' => 4.5,
                    'reviews_count' => 100,
                ]
            ],
            'meta' => [
                'trip_id' => $trip->id,
                'cached' => true,
            ]
        ];

        // Mock the cache to return our cached result
        Cache::shouldReceive('remember')
            ->once()
            ->andReturn($cachedResult);

        $result = $this->service->suggestForTrip($trip, $query);

        $this->assertInstanceOf(SuggestedPlaceCollection::class, $result);
        $this->assertCount(1, $result->items);
        $this->assertEquals('Cached Place', $result->items[0]['name']);
    }

    #[Test]
    public function it_returns_empty_collection_when_no_origins_available()
    {
        // Enable suggestions
        config(['ai.suggestions.enabled' => true]);

        $trip = Trip::factory()->create(['owner_id' => $this->user->id]);
        $query = new PlaceSuggestionQuery(
            basedOnPlaceId: null,
            limit: 10,
            radiusMeters: 1000,
            locale: 'en'
        );

        // Mock preferences
        $this->preferences->shouldReceive('getGroupPreferences')
            ->once()
            ->with($trip)
            ->andReturn(['food' => 1.0]);

        // Mock cache to compute the value
        Cache::shouldReceive('remember')
            ->once()
            ->andReturnUsing(function ($key, $ttl, $callback) {
                return $callback();
            });

        $result = $this->service->suggestForTrip($trip, $query);

        $this->assertInstanceOf(SuggestedPlaceCollection::class, $result);
        $this->assertEmpty($result->items);
        $this->assertTrue($result->meta['empty'] ?? false);
    }

    #[Test]
    public function it_processes_candidates_and_returns_suggested_places()
    {
        // Enable suggestions
        config(['ai.suggestions.enabled' => true]);

        $trip = Trip::factory()->create([
            'owner_id' => $this->user->id,
            'start_latitude' => 52.2297,  // Example coordinates (Warsaw)
            'start_longitude' => 21.0122,
        ]);
        $place = Place::factory()->create();
        $query = new PlaceSuggestionQuery(
            basedOnPlaceId: $place->id,
            limit: 10,
            radiusMeters: 1000,
            locale: 'en'
        );

        // Mock preferences
        $this->preferences->shouldReceive('getGroupPreferences')
            ->once()
            ->with($trip)
            ->andReturn(['food' => 1.0]);

        // Mock candidate provider to return some candidates
        $this->candidateProvider->shouldReceive('getCandidates')
            ->once()
            ->andReturn([
                [
                    'name' => 'Test Restaurant',
                    'category' => 'food',  // Changed from 'restaurant' to 'food'
                    'source' => 'test',
                    'internal_place_id' => $place->id,
                    'rating' => 4.5,
                    'reviews_count' => 100,
                ]
            ]);

        // Mock reasoner to return scores and reasons
        $this->reasoner->shouldReceive('rankAndExplain')
            ->once()
            ->andReturn([
                ['score' => 0.9, 'reason' => 'Good match for food preferences']
            ]);

        // Mock the AI enhancer
        $this->aiEnhancer->shouldReceive('enhancePlaces')
            ->once()
            ->andReturn([
                'test_restaurant' => ['reason' => 'Great place to eat!', 'score' => 0.9]
            ]);

        // Mock cache to compute the value
        Cache::shouldReceive('remember')
            ->once()
            ->andReturnUsing(function ($key, $ttl, $callback) {
                return $callback();
            });

        $result = $this->service->suggestForTrip($trip, $query);

        $this->assertInstanceOf(SuggestedPlaceCollection::class, $result);
        $this->assertCount(1, $result->items);
        $this->assertEquals('Test Restaurant', $result->items[0]->name);
        $this->assertEquals('food', $result->items[0]->category);
        $this->assertEquals(4.5, $result->items[0]->rating);
        $this->assertEquals(100, $result->items[0]->reviewsCount);
    }

    protected function tearDown(): void
    {
        // Clean up the database
        if (isset($this->user)) {
            $this->user->delete();
        }
        
        Mockery::close();
        parent::tearDown();
    }
}
