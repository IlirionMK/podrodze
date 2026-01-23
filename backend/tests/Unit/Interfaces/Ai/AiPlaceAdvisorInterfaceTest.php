<?php

namespace Tests\Unit\Interfaces\Ai;

use App\DTO\Ai\PlaceSuggestionQuery;
use App\DTO\Ai\SuggestedPlaceCollection;
use App\DTO\Ai\SuggestedPlace;
use App\Interfaces\Ai\AiPlaceAdvisorInterface;
use App\Models\Trip;
use Mockery;
use Tests\TestCase;

class AiPlaceAdvisorInterfaceTest extends TestCase
{
    private AiPlaceAdvisorInterface $advisor;
    private Trip $trip;
    private PlaceSuggestionQuery $query;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->advisor = Mockery::mock(AiPlaceAdvisorInterface::class);
        $this->trip = Trip::factory()->make();
        $this->query = new PlaceSuggestionQuery(
            basedOnPlaceId: 1, // Example place ID
            limit: 10,
            radiusMeters: 5000,
            locale: 'en'
        );
    }

    public function test_suggest_for_trip_returns_suggested_place_collection()
    {
        $suggestedPlace = new SuggestedPlace(
            source: 'test',
            internalPlaceId: 1,
            externalId: 'ext123',
            name: 'Test Place',
            category: 'test',
            rating: 4.5,
            reviewsCount: 100,
            lat: 52.2297,
            lon: 21.0122,
            distanceMeters: 1000,
            nearPlaceName: 'Nearby Location',
            estimatedVisitMinutes: 60,
            score: 0.9,
            reason: 'Test reason',
            addPayload: []
        );
        
        $expectedCollection = new SuggestedPlaceCollection([$suggestedPlace]);
        
        $this->advisor->shouldReceive('suggestForTrip')
            ->once()
            ->with($this->trip, $this->query)
            ->andReturn($expectedCollection);
            
        $result = $this->advisor->suggestForTrip($this->trip, $this->query);
        
        $this->assertInstanceOf(SuggestedPlaceCollection::class, $result);
        $this->assertCount(1, $result->items);
        $this->assertInstanceOf(SuggestedPlace::class, $result->items[0]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
