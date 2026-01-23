<?php

namespace Tests\Unit\Interfaces\Ai;

use App\DTO\Ai\PlaceSuggestionQuery;
use App\Interfaces\Ai\PlacesCandidateProviderInterface;
use App\Models\Trip;
use Mockery;
use Tests\TestCase;

class PlacesCandidateProviderInterfaceTest extends TestCase
{
    private PlacesCandidateProviderInterface $provider;
    private Trip $trip;
    private PlaceSuggestionQuery $query;
    private array $preferences;
    private array $context;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->provider = Mockery::mock(PlacesCandidateProviderInterface::class);
        $this->trip = Trip::factory()->make();
        $this->query = new PlaceSuggestionQuery(
            basedOnPlaceId: null,
            limit: 10,
            radiusMeters: 5000,
            locale: 'en'
        );
        $this->preferences = ['preference1' => 'value1'];
        $this->context = ['context1' => 'value1'];
    }

    public function test_get_candidates_returns_expected_structure()
    {
        $expectedCandidates = [
            [
                'source' => 'test_source',
                'name' => 'Test Place',
                'category' => 'Museum',
                'rating' => 4.5,
                'lat' => 52.2297,
                'lon' => 21.0122,
                'distance_m' => 1000
            ]
        ];
        
        $this->provider->shouldReceive('getCandidates')
            ->once()
            ->with(
                $this->trip,
                $this->query,
                $this->preferences,
                $this->context
            )
            ->andReturn($expectedCandidates);
            
        $result = $this->provider->getCandidates(
            $this->trip,
            $this->query,
            $this->preferences,
            $this->context
        );
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey(0, $result);
        $this->assertArrayHasKey('source', $result[0]);
        $this->assertArrayHasKey('name', $result[0]);
        $this->assertArrayHasKey('category', $result[0]);
        $this->assertArrayHasKey('lat', $result[0]);
        $this->assertArrayHasKey('lon', $result[0]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
