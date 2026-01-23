<?php

namespace Tests\Unit\Http\Resources;

use App\Http\Resources\ItineraryResource;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ItineraryResourceTest extends TestCase
{
    #[Test]
    public function it_transforms_itinerary_to_array(): void
    {
        $itineraryData = (object) [
            'trip_id' => 1,
            'day_count' => 3,
            'schedule' => [
                (object) [
                    'day' => 1,
                    'places' => [
                        (object) [
                            'id' => 10,
                            'name' => 'Central Park',
                            'category_slug' => 'park',
                            'score' => 4.5,
                            'distance_m' => 500,
                        ],
                        (object) [
                            'id' => 11,
                            'name' => 'Metropolitan Museum',
                            'category_slug' => 'museum',
                            'score' => 4.8,
                            'distance_m' => 1200,
                        ],
                    ],
                ],
                (object) [
                    'day' => 2,
                    'places' => [
                        (object) [
                            'id' => 12,
                            'name' => 'Brooklyn Bridge',
                            'category_slug' => 'landmark',
                            'score' => 4.7,
                            'distance_m' => 800,
                        ],
                    ],
                ],
            ],
            'cache_info' => (object) [
                'cached' => true,
                'cached_at' => '2025-01-15T10:30:00Z',
                'expires_in' => 3600,
                'source' => 'ai',
                'mode' => 'optimized',
                'algorithm' => 'genetic',
                'origin' => 'user_request',
            ],
        ];

        $resource = new ItineraryResource($itineraryData);
        $result = $resource->toArray(request());

        $this->assertEquals(1, $result['trip_id']);
        $this->assertEquals(3, $result['day_count']);
        $this->assertCount(2, $result['schedule']);

        // Check first day
        $firstDay = $result['schedule'][0];
        $this->assertEquals(1, $firstDay['day']);
        $this->assertCount(2, $firstDay['places']);

        $firstPlace = $firstDay['places'][0];
        $this->assertEquals(10, $firstPlace['id']);
        $this->assertEquals('Central Park', $firstPlace['name']);
        $this->assertEquals('park', $firstPlace['category_slug']);
        $this->assertEquals(4.5, $firstPlace['score']);
        $this->assertEquals(500, $firstPlace['distance_m']);

        // Check cache info
        $cache = $result['cache_info'];
        $this->assertTrue($cache['cached']);
        $this->assertEquals('2025-01-15T10:30:00Z', $cache['cached_at']);
        $this->assertEquals(3600, $cache['expires_in']);
        $this->assertEquals('ai', $cache['source']);
        $this->assertEquals('optimized', $cache['mode']);
        $this->assertEquals('genetic', $cache['algorithm']);
        $this->assertEquals('user_request', $cache['origin']);
    }

    #[Test]
    public function it_handles_missing_data_with_defaults(): void
    {
        $itineraryData = new \stdClass();

        $resource = new ItineraryResource($itineraryData);
        $result = $resource->toArray(request());

        $this->assertEquals(0, $result['trip_id']);
        $this->assertEquals(0, $result['day_count']);
        $this->assertEmpty($result['schedule']);

        $cache = $result['cache_info'];
        $this->assertFalse($cache['cached']);
        $this->assertNull($cache['cached_at']);
        $this->assertNull($cache['expires_in']);
        $this->assertNull($cache['source']);
        $this->assertNull($cache['mode']);
        $this->assertNull($cache['algorithm']);
        $this->assertNull($cache['origin']);
    }

    #[Test]
    public function it_handles_array_format_data(): void
    {
        $itineraryData = (object) [
            'trip_id' => 2,
            'day_count' => 1,
            'schedule' => [
                [
                    'day' => 1,
                    'places' => [
                        [
                            'id' => 20,
                            'name' => 'Statue of Liberty',
                            'category_slug' => 'landmark',
                            'score' => 4.9,
                            'distance_m' => 2000,
                        ],
                    ],
                ],
            ],
            'cache_info' => [
                'cached' => false,
                'source' => 'manual',
            ],
        ];

        $resource = new ItineraryResource($itineraryData);
        $result = $resource->toArray(request());

        $this->assertEquals(2, $result['trip_id']);
        $this->assertEquals(1, $result['day_count']);
        $this->assertCount(1, $result['schedule']);

        $place = $result['schedule'][0]['places'][0];
        $this->assertEquals(20, $place['id']);
        $this->assertEquals('Statue of Liberty', $place['name']);
        $this->assertEquals('landmark', $place['category_slug']);
        $this->assertEquals(4.9, $place['score']);
        $this->assertEquals(2000, $place['distance_m']);

        $this->assertFalse($result['cache_info']['cached']);
        $this->assertEquals('manual', $result['cache_info']['source']);
    }

    #[Test]
    public function it_returns_correct_structure(): void
    {
        $itineraryData = (object) [
            'trip_id' => 1,
            'day_count' => 1,
            'schedule' => [],
            'cache_info' => (object) [],
        ];

        $resource = new ItineraryResource($itineraryData);
        $result = $resource->toArray(request());

        $expectedKeys = ['trip_id', 'day_count', 'schedule', 'cache_info'];
        $this->assertEquals($expectedKeys, array_keys($result));

        $cacheKeys = ['cached', 'cached_at', 'expires_in', 'source', 'mode', 'algorithm', 'origin'];
        $this->assertEquals($cacheKeys, array_keys($result['cache_info']));
    }
}
