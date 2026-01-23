<?php

namespace Tests\Unit\Services\External;

use App\Services\External\GooglePlacesService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GooglePlacesServiceTest extends TestCase
{
    private GooglePlacesService $service;
    private string $testApiKey = 'test-api-key';

    protected function setUp(): void
    {
        parent::setUp();
        
        // Set up test configuration
        config(['services.google.maps_server_key' => $this->testApiKey]);
        config(['app.url' => 'http://localhost']);

        $this->service = new GooglePlacesService();

        // Mock cache to always return null (not found)
        Cache::shouldReceive('get')->andReturn(null);
        Cache::shouldReceive('remember')->andReturnUsing(function ($key, $ttl, $callback) {
            return $callback();
        });
        Cache::shouldReceive('put')->andReturn(true);
        Cache::shouldReceive('forget')->andReturn(true);
    }

    public function test_it_fetches_nearby_places()
    {
        $lat = 52.23;
        $lon = 21.01;
        $radius = 1000;

        $mockResponse = [
            'places' => [
                [
                    'id' => 'test-place-id',
                    'displayName' => ['text' => 'Test Place'],
                    'location' => ['latitude' => 52.23, 'longitude' => 21.01],
                    'types' => ['restaurant'],
                    'rating' => 4.5,
                    'userRatingCount' => 100,
                    'formattedAddress' => '123 Test St'
                ]
            ]
        ];

        // Mock the HTTP client to return our test response
        Http::fake([
            'places.googleapis.com/v1/places:searchNearby' => Http::response($mockResponse, 200, [
                'Content-Type' => 'application/json'
            ])
        ]);

        $results = $this->service->fetchNearby($lat, $lon, $radius);

        $this->assertIsArray($results);
        $this->assertNotEmpty($results);
        $this->assertEquals('Test Place', $results[0]['name']);
        $this->assertEquals('test-place-id', $results[0]['place_id']);
        $this->assertEquals(52.23, $results[0]['lat']);
        $this->assertEquals(21.01, $results[0]['lon']);
    }

    public function test_it_gets_place_details()
    {
        $placeId = 'test-place-id';
        $mockResponse = [
            'status' => 'OK',
            'result' => [
                'place_id' => 'test-place-id',
                'name' => 'Test Place',
                'formatted_address' => '123 Test St',
                'geometry' => ['location' => ['lat' => 52.23, 'lng' => 21.01]],
                'types' => ['restaurant'],
                'rating' => 4.5,
                'user_ratings_total' => 100,
                'vicinity' => '123 Test St',
                'opening_hours' => ['open_now' => true],
                'website' => 'https://example.com',
                'international_phone_number' => '+48123456789'
            ]
        ];

        // Mock the HTTP client to return our test response
        Http::fake([
            'maps.googleapis.com/maps/api/place/details/json*' => Http::response($mockResponse, 200, [
                'Content-Type' => 'application/json'
            ])
        ]);

        $details = $this->service->getPlaceDetails($placeId);

        $this->assertIsArray($details);
        $this->assertEquals('Test Place', $details['name']);
        $this->assertEquals(52.23, $details['lat']);
        $this->assertEquals(21.01, $details['lon']);
        $this->assertEquals('test-place-id', $details['place_id']);
        $this->assertEquals('food', $details['category_slug']);
        $this->assertArrayHasKey('meta', $details);
        $this->assertEquals('123 Test St', $details['meta']['address']);
    }

    public function test_it_handles_place_details_errors()
    {
        $placeId = 'invalid-place-id';

        // Mock error response
        Http::fake([
            'maps.googleapis.com/maps/api/place/details/json*' => Http::response([
                'status' => 'INVALID_REQUEST',
                'error_message' => 'Invalid request'
            ], 200, ['Content-Type' => 'application/json'])
        ]);

        $details = $this->service->getPlaceDetails($placeId);

        $this->assertNull($details);
    }
}
