<?php

namespace Tests\Unit\Services\Ai\Candidates;

use App\DTO\Ai\PlaceSuggestionQuery;
use App\Models\Place;
use App\Models\Trip;
use App\Services\Ai\CategoryNormalizer;
use App\Services\Ai\Candidates\DatabasePlacesCandidateProvider;
use App\Services\External\GooglePlacesService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DatabasePlacesCandidateProviderTest extends TestCase
{
    use RefreshDatabase;

    private DatabasePlacesCandidateProvider $provider;
    private CategoryNormalizer $categoryNormalizer;
    private $categoryNormalizerMock;
    private GooglePlacesService $googlePlaces;
    private $user;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create a test user to use as owner
        $this->user = \App\Models\User::factory()->create();
        
        // Create a real CategoryNormalizer instance (class is final, cannot be mocked)
        $this->categoryNormalizerMock = new CategoryNormalizer();
        
        $this->googlePlaces = \Mockery::mock(\App\Services\External\GooglePlacesService::class);
        
        // Initialize the provider
        $this->provider = new DatabasePlacesCandidateProvider(
            $this->categoryNormalizerMock,
            $this->googlePlaces
        );
        
        // Set the auth user
        $this->actingAs($this->user);
    }

    #[Test]
    public function it_returns_empty_array_when_no_origins_provided(): void
    {
        $trip = Trip::factory()->create(['owner_id' => $this->user->id]);
        $query = new PlaceSuggestionQuery(
            basedOnPlaceId: null,
            limit: 10,
            radiusMeters: 1000,
            locale: 'en'
        );

        $result = $this->provider->getCandidates($trip, $query, [], []);
        $this->assertEmpty($result);
    }

    #[Test]
    public function it_returns_database_candidates_within_radius(): void
    {
        $trip = Trip::factory()->create(['owner_id' => $this->user->id]);
        
        // Create test places
        $place1 = Place::factory()->create([
            'name' => 'Nearby Restaurant',
            'category_slug' => 'food',
            'rating' => 4.5,
            'location' => DB::raw("ST_GeogFromText('POINT(21.0122 52.2297)')"), // Warsaw coordinates
            'meta' => json_encode(['user_ratings_total' => 100])
        ]);
        $place2 = Place::factory()->create([
            'name' => 'Far Away Museum',
            'category_slug' => 'museum',
            'rating' => 4.0,
            'location' => DB::raw("ST_GeogFromText('POINT(19.9445 50.0647)')"), // Krakow coordinates
            'meta' => json_encode(['user_ratings_total' => 50])
        ]);

        $query = new PlaceSuggestionQuery(
            basedOnPlaceId: null,
            limit: 10,
            radiusMeters: 5000,
            locale: 'en'
        );

        $origins = [
            ['lat' => 52.2297, 'lon' => 21.0122, 'name' => 'Warsaw Center']
        ];

        $result = $this->provider->getCandidates($trip, $query, ['food' => 1.0], ['origins' => $origins]);
        $this->assertCount(1, $result);
        $this->assertEquals('Nearby Restaurant', $result[0]['name']);
        $this->assertEquals('internal_db', $result[0]['source']);
        $this->assertEquals($place1->id, $result[0]['internal_place_id']);
        $this->assertEquals('food', $result[0]['category']);
        $this->assertEquals(4.5, $result[0]['rating']);
        $this->assertEquals(100, $result[0]['reviews_count']);
    }

    #[Test]
    public function it_filters_out_existing_trip_places(): void
    {
        $trip = Trip::factory()->create(['owner_id' => $this->user->id]);
        $place = Place::factory()->create([
            'name' => 'Test Place',
            'location' => DB::raw("ST_GeogFromText('POINT(21.0122 52.2297)')")
        ]);
        
        // Add place to trip
        $trip->places()->attach($place->id);

        $query = new PlaceSuggestionQuery(
            basedOnPlaceId: null,
            limit: 10,
            radiusMeters: 5000,
            locale: 'en'
        );

        $origins = [
            ['lat' => 52.2297, 'lon' => 21.0122, 'name' => 'Warsaw Center']
        ];

        $result = $this->provider->getCandidates($trip, $query, [], ['origins' => $origins]);
        $this->assertEmpty($result);
    }

    #[Test]
    public function it_calls_google_service_when_enabled_and_insufficient_results(): void
    {
        Config::set('ai.suggestions.external.enabled', true);
        
        $trip = Trip::factory()->create(['owner_id' => $this->user->id]);
        $query = new PlaceSuggestionQuery(
            basedOnPlaceId: null,
            limit: 10,
            radiusMeters: 5000,
            locale: 'en'
        );

        $origins = [
            ['lat' => 52.2297, 'lon' => 21.0122, 'name' => 'Warsaw Center']
        ];

        $googleResults = [
            [
                'place_id' => 'google123',
                'name' => 'Google Place',
                'category_slug' => 'food',
                'rating' => 4.2,
                'lat' => 52.2300,
                'lon' => 21.0125,
                'user_ratings_total' => 200
            ]
        ];

        $this->googlePlaces
            ->shouldReceive('fetchNearbyByPointAndPreferredCategories')
            ->once()
            ->with(52.2297, 21.0122, ['food'], 5000, 15, 'en')
            ->andReturn($googleResults);

        $result = $this->provider->getCandidates($trip, $query, ['food' => 1.0], ['origins' => $origins]);
        $this->assertEquals('Google Place', $result[0]['name']);
        $this->assertEquals('google', $result[0]['source']);
        $this->assertEquals('google:google123', $result[0]['external_id']);
        $this->assertEquals('other', $result[0]['category']);
        $this->assertEquals(4.2, $result[0]['rating']);
        $this->assertEquals(200, $result[0]['reviews_count']);
    }

    #[Test]
    public function it_handles_google_service_errors_gracefully(): void
    {
        Config::set('ai.suggestions.external.enabled', true);
        
        $trip = Trip::factory()->create(['owner_id' => $this->user->id]);
        $query = new PlaceSuggestionQuery(
            basedOnPlaceId: null,
            limit: 10,
            radiusMeters: 5000,
            locale: 'en'
        );

        $origins = [
            ['lat' => 52.2297, 'lon' => 21.0122, 'name' => 'Warsaw Center']
        ];

        $this->googlePlaces
            ->shouldReceive('fetchNearbyByPointAndPreferredCategories')
            ->once()
            ->andThrow(new \Exception('Google API error'));

        Log::shouldReceive('error')
            ->once()
            ->with("Google search failed for origin: Warsaw Center; Google API error");

        $result = $this->provider->getCandidates($trip, $query, ['food' => 1.0], ['origins' => $origins]);
        $this->assertEmpty($result);
    }

    #[Test]
    public function it_does_not_call_google_service_when_disabled(): void
    {
        Config::set('ai.suggestions.external.enabled', false);
        
        $trip = Trip::factory()->create(['owner_id' => $this->user->id]);
        $query = new PlaceSuggestionQuery(
            basedOnPlaceId: null,
            limit: 10,
            radiusMeters: 5000,
            locale: 'en'
        );

        $this->googlePlaces->shouldNotReceive('fetchNearbyByPointAndPreferredCategories');

        $result = $this->provider->getCandidates($trip, $query, ['food' => 1.0], ['origins' => []]);
        $this->assertEmpty($result);
    }

    #[Test]
    public function it_merges_database_and_google_results_without_duplicates(): void
    {
        Config::set('ai.suggestions.external.enabled', true);
        
        $trip = Trip::factory()->create([
            'owner_id' => $this->user->id,
            'start_latitude' => 52.2297,
            'start_longitude' => 21.0122,
        ]);
        
        // Create database place
        $place = Place::factory()->create([
            'name' => 'DB Place',
            'category_slug' => 'food',
            'rating' => 4.5,
            'location' => DB::raw("ST_GeogFromText('POINT(21.0122 52.2297)')"),
            'google_place_id' => 'google123',
            'meta' => json_encode(['user_ratings_total' => 2858])
        ]);
        
        // Google returns a different place
        $googleResults = [
            [
                'place_id' => 'google456', // Different from DB place
                'name' => 'Google Place',
                'category_slug' => 'food',
                'rating' => 4.2,
                'lat' => 52.2300,
                'lon' => 21.0125,
                'user_ratings_total' => 200
            ]
        ];

        $this->googlePlaces
            ->shouldReceive('fetchNearbyByPointAndPreferredCategories')
            ->once()
            ->with(52.2297, 21.0122, ['food'], 5000, 15, 'en')
            ->andReturn($googleResults);

        
        $query = new PlaceSuggestionQuery(
            basedOnPlaceId: null,
            limit: 10,
            radiusMeters: 5000,
            locale: 'en'
        );

        $origins = [
            ['lat' => 52.2297, 'lon' => 21.0122, 'name' => 'Warsaw Center']
        ];

        $result = $this->provider->getCandidates($trip, $query, ['food' => 1.0], ['origins' => $origins]);
        $this->assertCount(2, $result);
        
        // Database place should come first
        $this->assertEquals('DB Place', $result[0]['name']);
        $this->assertEquals('internal_db', $result[0]['source']);
        $this->assertEquals($place->id, $result[0]['internal_place_id']);
        $this->assertEquals('food', $result[0]['category']);
        $this->assertEquals(4.5, $result[0]['rating']);
        $this->assertEquals(2858, $result[0]['reviews_count']);
        
        // Only the new Google place should be included
        $this->assertEquals('Google Place', $result[1]['name']);
        $this->assertEquals('google', $result[1]['source']);
        $this->assertEquals('google:google456', $result[1]['external_id']);
        $this->assertEquals('other', $result[1]['category']);
        $this->assertEquals(4.2, $result[1]['rating']);
        $this->assertEquals(200, $result[1]['reviews_count']);
    }

    #[Test]
    public function it_calculates_distance_correctly(): void
    {
        $trip = Trip::factory()->create(['owner_id' => $this->user->id]);
        $place = Place::factory()->create([
            'name' => 'Test Place',
            'location' => DB::raw("ST_GeogFromText('POINT(21.0122 52.2297)')")
        ]);
        
        $query = new PlaceSuggestionQuery(
            basedOnPlaceId: null,
            limit: 10,
            radiusMeters: 5000,
            locale: 'en'
        );

        $origins = [
            ['lat' => 52.2297, 'lon' => 21.0122, 'name' => 'Warsaw Center']
        ];

        $result = $this->provider->getCandidates($trip, $query, [], ['origins' => $origins]);
        $this->assertCount(1, $result);
        $this->assertEquals(0, $result[0]['distance_m']); // Same coordinates
        $this->assertEquals('Warsaw Center', $result[0]['near_place_name']);
    }

    #[Test]
    public function it_finds_nearest_origin_when_multiple_provided(): void
    {
        $trip = Trip::factory()->create(['owner_id' => $this->user->id]);
        
        // Place closer to Warsaw than Krakow
        $place = Place::factory()->create([
            'name' => 'Test Place',
            'location' => DB::raw("ST_GeogFromText('POINT(21.0122 52.2297)')") // Warsaw
        ]);
        
        // Place further from Warsaw than Krakow
        $place2 = Place::factory()->create([
            'name' => 'Test Place 2',
            'location' => DB::raw("ST_GeogFromText('POINT(19.9445 50.0647)')"), // Krakow
        ]);
        
        $query = new PlaceSuggestionQuery(
            basedOnPlaceId: null,
            limit: 10,
            radiusMeters: 500000, // Large radius to include both
            locale: 'en'
        );

        $origins = [
            ['lat' => 52.2297, 'lon' => 21.0122, 'name' => 'Warsaw Center'],
            ['lat' => 19.9445, 'lon' => 50.0647, 'name' => 'Krakow Center']
        ];

        $result = $this->provider->getCandidates($trip, $query, [], ['origins' => $origins]);
        $this->assertCount(2, $result);
        
        // Both places should have Warsaw Center as nearest origin due to distance
        $this->assertEquals('Warsaw Center', $result[0]['near_place_name']);
        $this->assertEquals('Warsaw Center', $result[1]['near_place_name']);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
