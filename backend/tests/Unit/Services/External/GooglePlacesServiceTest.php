<?php

namespace Tests\Unit\Services\External;

use App\Services\External\GooglePlacesService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class GooglePlacesServiceTest extends TestCase
{
    private GooglePlacesService $service;
    private string $testApiKey = 'test-api-key';

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new GooglePlacesService($this->testApiKey);

        Http::fake();
    }

    #[Test]
    public function it_searches_places()
    {
        $query = 'restaurants in Paris';
        $mockResponse = [
            'results' => [
                ['name' => 'Test Restaurant', 'place_id' => '123']
            ]
        ];

        Http::fake([
            'maps.googleapis.com/maps/api/place/textsearch/json*' => Http::response($mockResponse)
        ]);

        $results = $this->service->searchPlaces($query);

        $this->assertIsArray($results);
        $this->assertArrayHasKey('results', $results);
        $this->assertEquals('Test Restaurant', $results['results'][0]['name']);
    }

    #[Test]
    public function it_gets_place_details()
    {
        $placeId = 'test-place-id';
        $mockResponse = [
            'result' => [
                'name' => 'Test Place',
                'formatted_address' => '123 Test St',
                'geometry' => [
                    'location' => ['lat' => 52.23, 'lng' => 21.01]
                ]
            ]
        ];

        Http::fake([
            'maps.googleapis.com/maps/api/place/details/json*' => Http::response($mockResponse)
        ]);

        $details = $this->service->getPlaceDetails($placeId);

        $this->assertIsArray($details);
        $this->assertEquals('Test Place', $details['result']['name']);
    }

    #[Test]
    public function it_searches_nearby_places()
    {
        $location = ['lat' => 52.23, 'lng' => 21.01];
        $radius = 1000;
        $type = 'restaurant';

        $mockResponse = [
            'results' => [
                ['name' => 'Nearby Restaurant', 'place_id' => '456']
            ]
        ];

        Http::fake([
            'maps.googleapis.com/maps/api/place/nearbysearch/json*' => Http::response($mockResponse)
        ]);

        $results = $this->service->searchNearby($location, $radius, $type);

        $this->assertIsArray($results);
        $this->assertCount(1, $results['results']);
        $this->assertEquals('Nearby Restaurant', $results['results'][0]['name']);
    }

    #[Test]
    public function it_handles_api_errors()
    {
        Http::fake([
            'maps.googleapis.com/*' => Http::response(['error_message' => 'Invalid request'], 400)
        ]);

        $result = $this->service->searchPlaces('');

        $this->assertArrayHasKey('error', $result);
        $this->assertEquals('Invalid request', $result['error']);
    }
}
