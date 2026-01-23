<?php

namespace Tests\Unit\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\PlaceController;
use App\Interfaces\PlaceInterface;
use App\Models\Place;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PlaceControllerTest extends TestCase
{
    use RefreshDatabase;

    private $placeService;
    private $controller;
    private $place;

    protected function setUp(): void
    {
        parent::setUp();

        $this->placeService = Mockery::mock(PlaceInterface::class);
        $this->controller = new PlaceController($this->placeService);
        $this->place = Place::factory()->create();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function it_returns_nearby_places()
    {
        $request = new Request([
            'lat' => 52.2297,
            'lon' => 21.0122,
            'radius' => 1000
        ]);

        $expectedPlaces = [
            (object) [
                'id' => 1, 
                'name' => 'Test Place',
                'google_place_id' => 'test123',
                'category_slug' => 'restaurant',
                'rating' => 4.5,
                'meta' => [],
                'created_at' => now(),
                'updated_at' => now()
            ]
        ];

        $this->placeService
            ->shouldReceive('nearbyWithSync')
            ->once()
            ->with(52.2297, 21.0122, 1000)
            ->andReturn([
                'places' => collect($expectedPlaces),
                'summary' => ['total' => 1, 'synced' => 1]
            ]);

        $response = $this->controller->nearby($request);
        $responseData = json_decode($response->getContent(), true);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertArrayHasKey('data', $responseData);
        $this->assertArrayHasKey('message', $responseData);
        $this->assertArrayHasKey('summary', $responseData);
        $this->assertEquals('Nearby places synchronized successfully', $responseData['message']);
        $this->assertEquals(1, $responseData['summary']['total']);
        $this->assertEquals(1, $responseData['summary']['synced']);
    }

    #[Test]
    public function it_validates_nearby_places_request()
    {
        $request = new Request([
            'lat' => 'invalid-lat',
            'lon' => 21.0122
        ]);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        
        try {
            $this->controller->nearby($request);
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertArrayHasKey('lat', $e->errors());
            throw $e;
        }
    }
    
    #[Test]
    public function it_handles_service_errors_gracefully()
    {
        $request = new Request([
            'lat' => 52.2297,
            'lon' => 21.0122
        ]);

        $this->placeService
            ->shouldReceive('nearbyWithSync')
            ->once()
            ->andThrow(new \DomainException('Service unavailable'));

        $response = $this->controller->nearby($request);
        $responseData = json_decode($response->getContent(), true);

        $this->assertEquals(400, $response->getStatusCode());
        $this->assertEquals('Service unavailable', $responseData['error']);
    }

    #[Test]
    public function it_autocompletes_place_search()
    {
        $request = new Request([
            'q' => 'restaurant'
        ]);

        $expectedItems = [
            ['id' => 1, 'name' => 'Restaurant 1', 'address' => 'Address 1'],
            ['id' => 2, 'name' => 'Restaurant 2', 'address' => 'Address 2']
        ];

        $this->placeService
            ->shouldReceive('googleAutocomplete')
            ->once()
            ->with(
                'restaurant',
                null,
                null,
                null,
                'pl',
                null
            )
            ->andReturn($expectedItems);

        $response = $this->controller->autocomplete($request);
        $responseData = json_decode($response->getContent(), true);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertArrayHasKey('data', $responseData);
        $this->assertCount(2, $responseData['data']);
        $this->assertEquals('Restaurant 1', $responseData['data'][0]['name']);
    }

    #[Test]
    public function it_validates_autocomplete_request()
    {
        $request = new Request([
            'q' => 'a' // Too short (min: 2)
        ]);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        
        try {
            $this->controller->autocomplete($request);
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertArrayHasKey('q', $e->errors());
            throw $e;
        }
    }

    #[Test]
    public function it_returns_google_place_details()
    {
        $googlePlaceId = 'ChIJrTLr-GyuEmsRBfyf1GD8etkg';
        $request = new Request([
            'language' => 'en'
        ]);

        $expectedDetails = [
            'id' => 1,
            'name' => 'Test Place',
            'address' => 'Test Address',
            'rating' => 4.5,
            'google_place_id' => $googlePlaceId
        ];

        $this->placeService
            ->shouldReceive('googleDetails')
            ->once()
            ->with(
                $googlePlaceId,
                'en',
                null
            )
            ->andReturn($expectedDetails);

        $response = $this->controller->googleDetails($request, $googlePlaceId);
        $responseData = json_decode($response->getContent(), true);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertArrayHasKey('data', $responseData);
        $this->assertEquals('Test Place', $responseData['data']['name']);
        $this->assertEquals($googlePlaceId, $responseData['data']['google_place_id']);
    }

    #[Test]
    public function it_handles_google_place_details_not_found()
    {
        $googlePlaceId = 'nonexistent';
        $request = new Request();

        $this->placeService
            ->shouldReceive('googleDetails')
            ->once()
            ->with(
                $googlePlaceId,
                'pl',
                null
            )
            ->andReturn(null);

        $response = $this->controller->googleDetails($request, $googlePlaceId);
        $responseData = json_decode($response->getContent(), true);

        $this->assertEquals(404, $response->getStatusCode());
        $this->assertEquals('Place not found', $responseData['message']);
    }

}
