<?php

namespace Tests\Unit\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\ItineraryController;
use App\Interfaces\ItineraryServiceInterface;
use App\Models\Trip;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ItineraryControllerTest extends TestCase
{
    use RefreshDatabase;

    private $itineraryService;
    private $controller;
    private $trip;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->itineraryService = Mockery::mock(ItineraryServiceInterface::class);
        $this->controller = new ItineraryController($this->itineraryService);
        $user = \App\Models\User::factory()->create();
        $this->trip = Trip::factory()->create(['owner_id' => $user->id]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function it_returns_itinerary_for_trip()
    {
        $this->withoutExceptionHandling();
        
        // Mock authorization
        $this->mock(\Illuminate\Contracts\Auth\Access\Gate::class)
            ->shouldReceive('authorize')
            ->with('view', $this->trip)
            ->andReturn(true);
        
        // Create a real Itinerary DTO since it's final
        $itineraryMock = new \App\DTO\Itinerary\Itinerary(
            trip_id: $this->trip->id,
            day_count: 0,
            schedule: []
        );
        
        $this->itineraryService
            ->shouldReceive('generate')
            ->once()
            ->with(Mockery::type(Trip::class))
            ->andReturn($itineraryMock);

        $response = $this->controller->generate($this->trip);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertJson($response->getContent());
    }

    #[Test]
    public function it_handles_itinerary_generation_errors()
    {
        $this->withoutExceptionHandling();
        
        // Mock authorization
        $this->mock(\Illuminate\Contracts\Auth\Access\Gate::class)
            ->shouldReceive('authorize')
            ->with('view', $this->trip)
            ->andReturn(true);
        
        $this->itineraryService
            ->shouldReceive('generate')
            ->once()
            ->with(Mockery::type(Trip::class))
            ->andThrow(new \DomainException('Error generating itinerary'));

        $response = $this->controller->generate($this->trip);

        $this->assertEquals(400, $response->getStatusCode());
        $this->assertJson($response->getContent());
        $this->assertStringContainsString('Error generating itinerary', $response->getContent());
    }

    #[Test]
    public function it_generates_full_route_with_valid_parameters()
    {
        $this->withoutExceptionHandling();
        
        // Mock authorization
        $this->mock(\Illuminate\Contracts\Auth\Access\Gate::class)
            ->shouldReceive('authorize')
            ->with('view', $this->trip)
            ->andReturn(true);
        
        // Create a real Itinerary DTO since it's final
        $itineraryMock = new \App\DTO\Itinerary\Itinerary(
            trip_id: $this->trip->id,
            day_count: 0,
            schedule: []
        );
            
        $request = new Request([
            'days' => 3,
            'radius' => 2000
        ]);
        
        $this->itineraryService
            ->shouldReceive('generateFullRoute')
            ->once()
            ->with(Mockery::type(Trip::class), 3, 2000)
            ->andReturn($itineraryMock);

        $response = $this->controller->generateFullRoute($request, $this->trip);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertJson($response->getContent());
    }
}
