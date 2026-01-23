<?php

namespace Tests\Unit\DTO\Ai;

use App\DTO\Ai\PlaceSuggestionQuery;
use Tests\TestCase;

class PlaceSuggestionQueryTest extends TestCase
{
    public function test_it_creates_from_array_with_defaults()
    {
        $data = [];
        $query = PlaceSuggestionQuery::fromArray($data);

        $this->assertNull($query->basedOnPlaceId);
        $this->assertEquals(8, $query->limit); // Default value from config
        $this->assertEquals(10000, $query->radiusMeters); // Default value from config
        $this->assertEquals('en', $query->locale);
    }

    public function test_it_creates_from_array_with_custom_values()
    {
        $data = [
            'based_on_place_id' => 123,
            'limit' => 5,
            'radius_m' => 1000,
            'locale' => 'pl',
        ];
        
        $query = PlaceSuggestionQuery::fromArray($data);

        $this->assertEquals(123, $query->basedOnPlaceId);
        $this->assertEquals(5, $query->limit);
        $this->assertEquals(1000, $query->radiusMeters);
        $this->assertEquals('pl', $query->locale);
    }

    public function test_it_handles_string_values()
    {
        $data = [
            'based_on_place_id' => '123',
            'limit' => '5',
            'radius_m' => '1000',
            'locale' => 123, // will be cast to string
        ];
        
        $query = PlaceSuggestionQuery::fromArray($data);

        $this->assertSame(123, $query->basedOnPlaceId);
        $this->assertSame(5, $query->limit);
        $this->assertSame(1000, $query->radiusMeters);
        $this->assertSame('123', $query->locale);
    }
}
