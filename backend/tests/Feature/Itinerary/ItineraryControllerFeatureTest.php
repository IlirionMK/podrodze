<?php
//
//namespace Tests\Feature\Itinerary;
//
//use App\Models\Trip;
//use App\Models\User;
//use Illuminate\Foundation\Testing\RefreshDatabase;
//use Laravel\Sanctum\Sanctum;
//use Tests\TestCase;
//use PHPUnit\Framework\Attributes\Test;
//use PHPUnit\Framework\Attributes\Group;
//use Mockery;
//
///**
// * Test suite for ItineraryController API endpoints.
// *
// * This test verifies the functionality of itinerary generation including:
// * 1. Generating simple one-day itineraries
// * 2. Generating full multi-day itineraries with custom parameters
// * 3. Aggregating preferences from trip members
// * 4. Authorization and access control for itinerary operations
// * 5. Validation of itinerary generation parameters
// * 6. Error handling for generation failures
// */
//#[Group('itinerary')]
//#[Group('ai')]
//#[Group('feature')]
//class ItineraryControllerFeatureTest extends TestCase
//{
//    use RefreshDatabase;
//
//    /** @var User Test trip owner */
//    protected User $owner;
//
//    /** @var User Test member user */
//    protected User $member;
//
//    /** @var Trip Test trip instance */
//    protected Trip $trip;
//
//    /**
//     * Set up the test environment.
//     * Creates test users and trip for itinerary testing.
//     */
//    protected function setUp(): void
//    {
//        parent::setUp();
//
//        $this->owner = User::factory()->create([
//            'name' => 'Trip Owner',
//            'email' => 'owner@example.com',
//        ]);
//
//        $this->member = User::factory()->create([
//            'name' => 'Trip Member',
//            'email' => 'member@example.com',
//        ]);
//
//        $this->trip = Trip::factory()->create([
//            'name' => 'Test Trip',
//            'start_date' => now(),
//            'end_date' => now()->addDays(7),
//            'owner_id' => $this->owner->id,
//            'start_latitude' => 52.2297,
//            'start_longitude' => 21.0122,
//        ]);
//
//        // Add member to trip
//        $this->trip->members()->attach($this->member->id, [
//            'role' => 'member',
//            'status' => 'accepted'
//        ]);
//
//        Sanctum::actingAs($this->owner);
//    }
//
//    /**
//     * Create a simple mock itinerary for testing
//     */
//    private function createMockItinerary(int $tripId, int $dayCount = 1, array $places = []): \App\DTO\Itinerary\Itinerary
//    {
//        if (empty($places)) {
//            $places = [
//                new \App\DTO\Itinerary\ItineraryPlace(
//                    id: 1,
//                    name: 'Test Place',
//                    category_slug: 'restaurant',
//                    score: 4.0,
//                    distance_m: 100.0
//                )
//            ];
//        }
//
//        $schedule = [];
//        for ($day = 1; $day <= $dayCount; $day++) {
//            $schedule[] = new \App\DTO\Itinerary\ItineraryDay(
//                day: $day,
//                places: $places
//            );
//        }
//
//        return new \App\DTO\Itinerary\Itinerary(
//            trip_id: $tripId,
//            day_count: $dayCount,
//            schedule: $schedule,
//            cache_info: ['cached' => false, 'source' => 'test']
//        );
//    }
//
//    #[Test]
//    public function it_generates_simple_itinerary()
//    {
//        $mockService = $this->mock(\App\Interfaces\ItineraryServiceInterface::class);
//
//        $expectedItinerary = $this->createMockItinerary($this->trip->id);
//
//        $mockService->shouldReceive('generate')
//            ->once()
//            ->with($this->trip)
//            ->andReturn($expectedItinerary);
//
//        $response = $this->getJson("/api/v1/trips/{$this->trip->id}/itinerary/generate");
//
//        $response->assertStatus(200)
//            ->assertJsonStructure([
//                'data' => [
//                    'trip_id',
//                    'day_count',
//                    'schedule' => [
//                        '*' => [
//                            'day',
//                            'places' => [
//                                '*' => [
//                                    'id',
//                                    'name',
//                                    'category_slug',
//                                    'score',
//                                    'distance_m'
//                                ]
//                            ]
//                        ]
//                    ],
//                    'cache_info'
//                ]
//            ]);
//
//        $responseData = $response->json('data');
//        $this->assertEquals($this->trip->id, $responseData['trip_id']);
//        $this->assertCount(1, $responseData['schedule']);
//        $this->assertEquals('Test Place', $responseData['schedule'][0]['places'][0]['name']);
//    }
//
//    #[Test]
//    public function it_generates_full_itinerary_with_custom_parameters()
//    {
//        $mockService = $this->mock(\App\Interfaces\ItineraryServiceInterface::class);
//
//        $expectedItinerary = $this->createMockItinerary($this->trip->id, 2);
//
//        $mockService->shouldReceive('generateFullRoute')
//            ->once()
//            ->with($this->trip, 3, 1500)
//            ->andReturn($expectedItinerary);
//
//        $response = $this->postJson("/api/v1/trips/{$this->trip->id}/itinerary/generate-full", [
//            'days' => 3,
//            'radius' => 1500
//        ]);
//
//        $response->assertStatus(200)
//            ->assertJsonStructure([
//                'data' => [
//                    'trip_id',
//                    'day_count',
//                    'schedule' => [
//                        '*' => [
//                            'day',
//                            'places' => [
//                                '*' => [
//                                    'id',
//                                    'name',
//                                    'category_slug',
//                                    'score',
//                                    'distance_m'
//                                ]
//                            ]
//                        ]
//                    ],
//                    'cache_info'
//                ]
//            ]);
//
//        $responseData = $response->json('data');
//        $this->assertEquals($this->trip->id, $responseData['trip_id']);
//        $this->assertCount(2, $responseData['schedule']);
//        $this->assertEquals(2, $responseData['day_count']);
//    }
//
//    #[Test]
//    public function it_aggregates_trip_member_preferences()
//    {
//        $mockService = $this->mock(\App\Interfaces\ItineraryServiceInterface::class);
//
//        $expectedAggregatedPrefs = [
//            'restaurant' => 1.5, // Average of preferences
//            'museum' => 1.0,
//            'park' => 0.5
//        ];
//
//        $mockService->shouldReceive('aggregatePreferences')
//            ->once()
//            ->with($this->trip)
//            ->andReturn($expectedAggregatedPrefs);
//
//        $response = $this->getJson("/api/v1/trips/{$this->trip->id}/preferences/aggregate");
//
//        $response->assertStatus(200)
//            ->assertJsonStructure([
//                'data',
//                'message'
//            ]);
//
//        $responseData = $response->json();
//        $this->assertEquals('Aggregated preferences calculated.', $responseData['message']);
//        $this->assertEquals($expectedAggregatedPrefs, $responseData['data']);
//    }
//
//    #[Test]
//    public function it_validates_full_itinerary_parameters()
//    {
//        $response = $this->postJson("/api/v1/trips/{$this->trip->id}/itinerary/generate-full", [
//            'days' => 0, // Invalid: must be at least 1
//            'radius' => 50 // Invalid: must be at least 100
//        ]);
//
//        $response->assertStatus(422)
//            ->assertJsonValidationErrors(['days', 'radius']);
//
//        $response = $this->postJson("/api/v1/trips/{$this->trip->id}/itinerary/generate-full", [
//            'days' => 31, // Invalid: maximum is 30
//            'radius' => 25000 // Invalid: maximum is 20000
//        ]);
//
//        $response->assertStatus(422)
//            ->assertJsonValidationErrors(['days', 'radius']);
//    }
//
//    #[Test]
//    public function it_handles_itinerary_generation_with_default_radius()
//    {
//        $mockService = $this->mock(\App\Interfaces\ItineraryServiceInterface::class);
//
//        $expectedItinerary = $this->createMockItinerary($this->trip->id, 0, []);
//
//        $mockService->shouldReceive('generateFullRoute')
//            ->once()
//            ->with($this->trip, 2, 2000) // Default radius should be 2000
//            ->andReturn($expectedItinerary);
//
//        $response = $this->postJson("/api/v1/trips/{$this->trip->id}/itinerary/generate-full", [
//            'days' => 2
//            // radius not provided, should use default
//        ]);
//
//        $response->assertStatus(200);
//    }
//
//    #[Test]
//    public function it_requires_authentication_for_itinerary_operations()
//    {
//        $this->refreshApplication();
//
//        $response = $this->getJson("/api/v1/trips/{$this->trip->id}/itinerary/generate");
//        $response->assertStatus(401);
//
//        $response = $this->postJson("/api/v1/trips/{$this->trip->id}/itinerary/generate-full", [
//            'days' => 3
//        ]);
//        $response->assertStatus(401);
//
//        $response = $this->getJson("/api/v1/trips/{$this->trip->id}/preferences/aggregate");
//        $response->assertStatus(401);
//    }
//
//    #[Test]
//    public function it_prevents_non_members_from_accessing_itinerary()
//    {
//        $nonMember = User::factory()->create();
//
//        $response = $this->actingAs($nonMember)
//            ->getJson("/api/v1/trips/{$this->trip->id}/itinerary/generate");
//
//        $response->assertStatus(403);
//    }
//
//    #[Test]
//    public function it_allows_members_to_access_itinerary()
//    {
//        $mockService = $this->mock(\App\Interfaces\ItineraryServiceInterface::class);
//        $mockService->shouldReceive('generate')
//            ->once()
//            ->with($this->trip)
//            ->andReturn($this->createMockItinerary($this->trip->id));
//
//        $response = $this->actingAs($this->member)
//            ->getJson("/api/v1/trips/{$this->trip->id}/itinerary/generate");
//
//        $response->assertStatus(200);
//    }
//
//    #[Test]
//    public function it_handles_itinerary_service_errors()
//    {
//        $mockService = $this->mock(\App\Interfaces\ItineraryServiceInterface::class);
//
//        $mockService->shouldReceive('generate')
//            ->once()
//            ->with($this->trip)
//            ->andThrow(new \DomainException('Insufficient trip data for generation'));
//
//        $response = $this->getJson("/api/v1/trips/{$this->trip->id}/itinerary/generate");
//
//        $response->assertStatus(400)
//            ->assertJson(['error' => 'Insufficient trip data for generation']);
//    }
//
//    #[Test]
//    public function it_handles_full_itinerary_service_errors()
//    {
//        $mockService = $this->mock(\App\Interfaces\ItineraryServiceInterface::class);
//
//        $mockService->shouldReceive('generateFullRoute')
//            ->once()
//            ->with($this->trip, 3, 2000)
//            ->andThrow(new \DomainException('Cannot generate itinerary: no places found'));
//
//        $response = $this->postJson("/api/v1/trips/{$this->trip->id}/itinerary/generate-full", [
//            'days' => 3,
//            'radius' => 2000
//        ]);
//
//        $response->assertStatus(400)
//            ->assertJson(['error' => 'Cannot generate itinerary: no places found']);
//    }
//
//    #[Test]
//    public function it_returns_404_for_nonexistent_trip()
//    {
//        $nonExistentId = 99999;
//
//        $response = $this->getJson("/api/v1/trips/{$nonExistentId}/itinerary/generate");
//        $response->assertStatus(404);
//
//        $response = $this->postJson("/api/v1/trips/{$nonExistentId}/itinerary/generate-full", [
//            'days' => 3
//        ]);
//        $response->assertStatus(404);
//
//        $response = $this->getJson("/api/v1/trips/{$nonExistentId}/preferences/aggregate");
//        $response->assertStatus(404);
//    }
//
//    #[Test]
//    public function it_handles_trip_without_location_for_itinerary_generation()
//    {
//        $tripWithoutLocation = Trip::factory()->create([
//            'owner_id' => $this->owner->id,
//            'start_latitude' => null,
//            'start_longitude' => null,
//        ]);
//
//        $mockService = $this->mock(\App\Interfaces\ItineraryServiceInterface::class);
//        $mockService->shouldReceive('generate')
//            ->once()
//            ->with($tripWithoutLocation)
//            ->andReturn($this->createMockItinerary($tripWithoutLocation->id));
//
//        $response = $this->getJson("/api/v1/trips/{$tripWithoutLocation->id}/itinerary/generate");
//
//        $response->assertStatus(200);
//    }
//
//    #[Test]
//    public function it_generates_itinerary_for_single_day_trip()
//    {
//        $singleDayTrip = Trip::factory()->create([
//            'owner_id' => $this->owner->id,
//            'start_date' => now(),
//            'end_date' => now(), // Same day
//            'start_latitude' => 52.2297,
//            'start_longitude' => 21.0122,
//        ]);
//
//        $mockService = $this->mock(\App\Interfaces\ItineraryServiceInterface::class);
//        $expectedItinerary = $this->createMockItinerary($singleDayTrip->id);
//
//        $mockService->shouldReceive('generate')
//            ->once()
//            ->with($singleDayTrip)
//            ->andReturn($expectedItinerary);
//
//        $response = $this->getJson("/api/v1/trips/{$singleDayTrip->id}/itinerary/generate");
//
//        $response->assertStatus(200);
//
//        $responseData = $response->json('data');
//        $this->assertEquals($singleDayTrip->id, $responseData['trip_id']);
//        $this->assertCount(1, $responseData['schedule']);
//    }
//
//    #[Test]
//    public function it_handles_empty_aggregated_preferences()
//    {
//        $mockService = $this->mock(\App\Interfaces\ItineraryServiceInterface::class);
//
//        $emptyPrefs = [];
//
//        $mockService->shouldReceive('aggregatePreferences')
//            ->once()
//            ->with($this->trip)
//            ->andReturn($emptyPrefs);
//
//        $response = $this->getJson("/api/v1/trips/{$this->trip->id}/preferences/aggregate");
//
//        $response->assertStatus(200);
//        $responseData = $response->json();
//        $this->assertEquals('Aggregated preferences calculated.', $responseData['message']);
//        $this->assertEmpty($responseData['data']);
//    }
//
//    #[Test]
//    public function it_validates_itinerary_generation_request_structure()
//    {
//        $response = $this->postJson("/api/v1/trips/{$this->trip->id}/itinerary/generate-full", [
//            'invalid_field' => 'value'
//        ]);
//
//        $response->assertStatus(422)
//            ->assertJsonValidationErrors(['days']);
//    }
//
//    protected function tearDown(): void
//    {
//        Mockery::close();
//        parent::tearDown();
//    }
//}
