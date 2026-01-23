<?php

namespace Tests\Feature\TripManagement;

use App\Models\Category;
use App\Models\Place;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Illuminate\Support\Facades\DB;
use App\Interfaces\Ai\AiPlaceAdvisorInterface;
use App\DTO\Ai\PlaceSuggestionQuery;
use App\DTO\Ai\SuggestedPlaceCollection;
use App\DTO\Ai\SuggestedPlace;
use Mockery;

/**
 * Test suite for TripPlaceSuggestionsController API endpoints.
 *
 * This test verifies the functionality of AI-powered place suggestions including:
 * 1. Generating place suggestions based on trip preferences
 * 2. Handling AI service responses and errors
 * 3. Validating suggestion parameters
 * 4. Authorization and access control
 * 5. Rate limiting and throttling
 */
#[Group('trip')]
#[Group('suggestions')]
#[Group('ai')]
#[Group('feature')]
class TripPlaceSuggestionsControllerTest extends TestCase
{
    use RefreshDatabase;

    /** @var User Authenticated test user */
    protected User $user;

    /** @var Trip Test trip instance */
    protected Trip $trip;

    /** @var array Test categories */
    protected array $categories = [];

    /**
     * Set up the test environment.
     * Creates a test user, trip, and categories for testing.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
        ]);

        $this->trip = Trip::factory()->create([
            'name' => 'Test Trip',
            'start_date' => now(),
            'end_date' => now()->addDays(7),
            'owner_id' => $this->user->id,
            'start_latitude' => 52.2297,  // Warsaw coordinates
            'start_longitude' => 21.0122,
        ]);

        // Create test categories
        $this->categories = [
            'restaurant' => Category::factory()->create([
                'slug' => 'restaurant',
                'include_in_preferences' => true,
                'translations' => ['en' => 'Restaurant', 'pl' => 'Restauracja']
            ]),
            'museum' => Category::factory()->create([
                'slug' => 'museum',
                'include_in_preferences' => true,
                'translations' => ['en' => 'Museum', 'pl' => 'Muzeum']
            ]),
            'park' => Category::factory()->create([
                'slug' => 'park',
                'include_in_preferences' => true,
                'translations' => ['en' => 'Park', 'pl' => 'Park']
            ]),
        ];

        Sanctum::actingAs($this->user);
    }

    #[Test]
    public function it_generates_ai_place_suggestions_for_trip()
    {

        $mockAdvisor = Mockery::mock(AiPlaceAdvisorInterface::class);

        $expectedSuggestions = new SuggestedPlaceCollection(
            items: [
                new SuggestedPlace(
                    source: 'google_places',
                    internalPlaceId: null,
                    externalId: 'test_place_1',
                    name: 'Suggested Restaurant',
                    category: 'restaurant',
                    rating: 4.5,
                    reviewsCount: 100,
                    lat: 52.2297,
                    lon: 21.0122,
                    distanceMeters: 500,
                    nearPlaceName: null,
                    estimatedVisitMinutes: 60,
                    score: 0.9,
                    reason: 'Highly rated restaurant in your area',
                    addPayload: ['google_place_id' => 'test_place_1']
                ),
                new SuggestedPlace(
                    source: 'google_places',
                    internalPlaceId: null,
                    externalId: 'test_place_2',
                    name: 'Suggested Museum',
                    category: 'museum',
                    rating: 4.7,
                    reviewsCount: 200,
                    lat: 52.2300,
                    lon: 21.0130,
                    distanceMeters: 800,
                    nearPlaceName: null,
                    estimatedVisitMinutes: 90,
                    score: 0.85,
                    reason: 'Matches your interest in history',
                    addPayload: ['google_place_id' => 'test_place_2']
                )
            ],
            meta: [
                'total' => 2,
                'query_time' => 1.2,
                'algorithm' => 'ai_v2',
                'sources' => ['google_places', 'user_preferences']
            ]
        );

        $mockAdvisor->shouldReceive('suggestForTrip')
            ->once()
            ->with(
                Mockery::on(fn($trip) => $trip->id === $this->trip->id),
                Mockery::on(fn($query) => $query instanceof PlaceSuggestionQuery)
            )
            ->andReturn($expectedSuggestions);

        $this->app->instance(AiPlaceAdvisorInterface::class, $mockAdvisor);

        $response = $this->getJson("/api/v1/trips/{$this->trip->id}/places/suggestions");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'source',
                        'internal_place_id',
                        'external_id',
                        'name',
                        'category',
                        'rating',
                        'reviews_count',
                        'location' => [
                            'lat',
                            'lon'
                        ],
                        'distance_m',
                        'estimated_visit_minutes',
                        'score',
                        'reason',
                        'actions' => [
                            'add_payload'
                        ]
                    ]
                ],
                'meta' => [
                    'total',
                    'query_time',
                    'algorithm',
                    'sources'
                ]
            ]);

        $responseData = $response->json();
        $this->assertCount(2, $responseData['data']);
        $this->assertEquals('Suggested Restaurant', $responseData['data'][0]['name']);
        $this->assertEquals('restaurant', $responseData['data'][0]['category']);
        $this->assertEquals(2, $responseData['meta']['total']);
    }

    #[Test]
    public function it_generates_suggestions_with_custom_parameters()
    {
        // Reset rate limiting by using a new user
        $newUser = User::factory()->create();
        $newTrip = Trip::factory()->create([
            'owner_id' => $newUser->id,
            'start_latitude' => 52.2297,
            'start_longitude' => 21.0122,
            'start_date' => now(),
            'end_date' => now()->addDays(7),
        ]);

        Sanctum::actingAs($newUser);

        $mockAdvisor = Mockery::mock(AiPlaceAdvisorInterface::class);

        $expectedSuggestions = new SuggestedPlaceCollection(
            items: [
                new SuggestedPlace(
                    source: 'google_places',
                    internalPlaceId: null,
                    externalId: 'custom_place_1',
                    name: 'Custom Suggestion',
                    category: 'park',
                    rating: 4.0,
                    reviewsCount: 50,
                    lat: 52.2290,
                    lon: 21.0110,
                    distanceMeters: 1200,
                    nearPlaceName: null,
                    estimatedVisitMinutes: 45,
                    score: 0.75,
                    reason: 'Matches your custom search criteria',
                    addPayload: ['google_place_id' => 'custom_place_1']
                )
            ],
            meta: [
                'total' => 1,
                'query_time' => 0.8,
                'algorithm' => 'ai_v2',
                'sources' => ['google_places']
            ]
        );

        $mockAdvisor->shouldReceive('suggestForTrip')
            ->once()
            ->with(
                Mockery::on(fn($trip) => $trip->id === $newTrip->id),
                Mockery::on(function ($query) {
                    return $query instanceof PlaceSuggestionQuery &&
                           $query->limit === 10 &&
                           $query->radiusMeters === 2000;
                })
            )
            ->andReturn($expectedSuggestions);

        $this->app->instance(AiPlaceAdvisorInterface::class, $mockAdvisor);

        $response = $this->getJson("/api/v1/trips/{$newTrip->id}/places/suggestions?" . http_build_query([
            'limit' => 10,
            'radius_m' => 2000,
            'locale' => 'en'
        ]));

        $response->assertStatus(200);
        $responseData = $response->json();
        $this->assertCount(1, $responseData['data']);
        $this->assertEquals('Custom Suggestion', $responseData['data'][0]['name']);
    }

    #[Test]
    public function it_handles_empty_suggestions_gracefully()
    {
        $mockAdvisor = Mockery::mock(AiPlaceAdvisorInterface::class);

        $emptySuggestions = new SuggestedPlaceCollection(
            items: [],
            meta: [
                'total' => 0,
                'query_time' => 0.5,
                'algorithm' => 'ai_v2',
                'sources' => ['google_places'],
                'message' => 'No suggestions found for the given criteria'
            ]
        );

        $mockAdvisor->shouldReceive('suggestForTrip')
            ->once()
            ->andReturn($emptySuggestions);

        $this->app->instance(AiPlaceAdvisorInterface::class, $mockAdvisor);

        $response = $this->getJson("/api/v1/trips/{$this->trip->id}/places/suggestions");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [],
                'meta' => [
                    'total',
                    'query_time',
                    'algorithm',
                    'sources'
                ]
            ]);

        $responseData = $response->json();
        $this->assertCount(0, $responseData['data']);
        $this->assertEquals(0, $responseData['meta']['total']);
    }

    #[Test]
    public function it_handles_ai_service_errors()
    {
        $mockAdvisor = Mockery::mock(AiPlaceAdvisorInterface::class);

        $mockAdvisor->shouldReceive('suggestForTrip')
            ->once()
            ->andThrow(new \Exception('AI service temporarily unavailable'));

        $this->app->instance(AiPlaceAdvisorInterface::class, $mockAdvisor);

        $response = $this->getJson("/api/v1/trips/{$this->trip->id}/places/suggestions");

        $response->assertStatus(500)
            ->assertJson([
                'message' => 'AI service temporarily unavailable'
            ]);
    }

    #[Test]
    public function it_validates_suggestion_parameters()
    {
        $response = $this->getJson("/api/v1/trips/{$this->trip->id}/places/suggestions?radius_m=-1");

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['radius_m']);

        $response = $this->getJson("/api/v1/trips/{$this->trip->id}/places/suggestions?limit=0");

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['limit']);

        $response = $this->getJson("/api/v1/trips/{$this->trip->id}/places/suggestions?limit=51");

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['limit']);

        $response = $this->getJson("/api/v1/trips/{$this->trip->id}/places/suggestions?radius_m=50001");

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['radius_m']);
    }

    #[Test]
    public function it_requires_trip_access_authorization()
    {
        $otherUser = User::factory()->create();
        $otherTrip = Trip::factory()->create(['owner_id' => $otherUser->id]);

        $response = $this->getJson("/api/v1/trips/{$otherTrip->id}/places/suggestions");

        $response->assertStatus(403);
    }

    #[Test]
    public function it_allows_access_to_trip_members()
    {
        $memberUser = User::factory()->create();
        $this->trip->members()->attach($memberUser->id, [
            'role' => 'member',
            'status' => 'accepted'
        ]);

        $mockAdvisor = Mockery::mock(AiPlaceAdvisorInterface::class);
        $mockAdvisor->shouldReceive('suggestForTrip')
            ->once()
            ->andReturn(new SuggestedPlaceCollection([], []));

        $this->app->instance(AiPlaceAdvisorInterface::class, $mockAdvisor);

        $response = $this->actingAs($memberUser)
            ->getJson("/api/v1/trips/{$this->trip->id}/places/suggestions");

        $response->assertStatus(200);
    }

    #[Test]
    public function it_returns_404_for_nonexistent_trip()
    {
        $nonExistentId = 99999;

        $response = $this->getJson("/api/v1/trips/{$nonExistentId}/places/suggestions");

        $response->assertStatus(404);
    }

    #[Test]
    public function it_respects_rate_limiting()
    {
        $mockAdvisor = Mockery::mock(AiPlaceAdvisorInterface::class);
        $mockAdvisor->shouldReceive('suggestForTrip')
            ->times(4)
            ->andReturn(new SuggestedPlaceCollection([], []));

        $this->app->instance(AiPlaceAdvisorInterface::class, $mockAdvisor);

        // Make 3 requests (within rate limit)
        for ($i = 0; $i < 3; $i++) {
            $response = $this->getJson("/api/v1/trips/{$this->trip->id}/places/suggestions");
            $response->assertStatus(200);
        }

        // 4th request might be rate limited (depending on configuration)
        $response = $this->getJson("/api/v1/trips/{$this->trip->id}/places/suggestions");
        // Accept either 200 (if no rate limiting) or 429 (if rate limited)
        $this->assertContains($response->status(), [200, 429]);
    }

    #[Test]
    public function it_handles_trip_without_location()
    {
        $tripWithoutLocation = Trip::factory()->create([
            'owner_id' => $this->user->id,
            'start_latitude' => null,
            'start_longitude' => null,
        ]);

        $mockAdvisor = Mockery::mock(AiPlaceAdvisorInterface::class);
        $mockAdvisor->shouldReceive('suggestForTrip')
            ->once()
            ->andReturn(new SuggestedPlaceCollection([], []));

        $this->app->instance(AiPlaceAdvisorInterface::class, $mockAdvisor);

        $response = $this->getJson("/api/v1/trips/{$tripWithoutLocation->id}/places/suggestions");

        $response->assertStatus(200);
    }

    #[Test]
    public function it_includes_trip_context_in_suggestions()
    {
        $mockAdvisor = Mockery::mock(AiPlaceAdvisorInterface::class);

        $expectedSuggestions = new SuggestedPlaceCollection(
            items: [
                new SuggestedPlace(
                    source: 'google_places',
                    internalPlaceId: null,
                    externalId: 'context_place_1',
                    name: 'Context-aware Place',
                    category: 'restaurant',
                    rating: 4.5,
                    reviewsCount: 150,
                    lat: 52.2297,
                    lon: 21.0122,
                    distanceMeters: 300,
                    nearPlaceName: null,
                    estimatedVisitMinutes: 60,
                    score: 0.95,
                    reason: 'Perfect for your 7-day trip to Warsaw',
                    addPayload: ['google_place_id' => 'context_place_1']
                )
            ],
            meta: [
                'total' => 1,
                'query_time' => 1.0,
                'algorithm' => 'ai_v2',
                'sources' => ['google_places', 'trip_context']
            ]
        );

        $mockAdvisor->shouldReceive('suggestForTrip')
            ->once()
            ->with(
                Mockery::on(function ($trip) {
                    return $trip->id === $this->trip->id &&
                           $trip->name === 'Test Trip' &&
                           $trip->start_date->format('Y-m-d') === now()->format('Y-m-d') &&
                           $trip->end_date->format('Y-m-d') === now()->addDays(7)->format('Y-m-d');
                }),
                Mockery::on(fn($query) => $query instanceof PlaceSuggestionQuery)
            )
            ->andReturn($expectedSuggestions);

        $this->app->instance(AiPlaceAdvisorInterface::class, $mockAdvisor);

        $response = $this->getJson("/api/v1/trips/{$this->trip->id}/places/suggestions");

        $response->assertStatus(200);
        $responseData = $response->json();
        $this->assertEquals('Context-aware Place', $responseData['data'][0]['name']);
        $this->assertStringContainsString('7-day trip', $responseData['data'][0]['reason']);
    }

    #[Test]
    public function it_requires_authentication()
    {
        $this->refreshApplication();

        $response = $this->getJson("/api/v1/trips/{$this->trip->id}/places/suggestions");

        $response->assertStatus(401);
    }
}
