<?php

namespace Tests\Unit\Interfaces;

use App\Interfaces\PreferenceAggregatorServiceInterface;
use App\Models\Trip;
use Mockery;
use Tests\TestCase;

class PreferenceAggregatorServiceInterfaceTest extends TestCase
{
    private PreferenceAggregatorServiceInterface $preferenceAggregator;
    private Trip $trip;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        $this->preferenceAggregator = Mockery::mock(PreferenceAggregatorServiceInterface::class);
        $this->trip = Trip::factory()->make();
    }
    
    public function test_get_group_preferences_returns_preferences_array()
    {
        $expectedPreferences = [
            'museum' => 1.8,
            'food' => 2.0,
            'nature' => 0.5,
            'shopping' => 1.2,
            'history' => 1.5,
        ];
        
        $this->preferenceAggregator->shouldReceive('getGroupPreferences')
            ->once()
            ->with($this->trip)
            ->andReturn($expectedPreferences);
            
        $result = $this->preferenceAggregator->getGroupPreferences($this->trip);
        
        $this->assertSame($expectedPreferences, $result);
        $this->assertIsArray($result);
        $this->assertArrayHasKey('museum', $result);
        $this->assertArrayHasKey('food', $result);
    }
    
    public function test_get_group_preferences_handles_empty_preferences()
    {
        $expectedPreferences = [];
        
        $this->preferenceAggregator->shouldReceive('getGroupPreferences')
            ->once()
            ->with($this->trip)
            ->andReturn($expectedPreferences);
            
        $result = $this->preferenceAggregator->getGroupPreferences($this->trip);
        
        $this->assertEmpty($result);
        $this->assertIsArray($result);
    }
}
