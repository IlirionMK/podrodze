<?php

namespace Tests\Unit\Http\Resources;

use App\DTO\Trip\TripPlace;
use App\Http\Resources\TripPlaceResource;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TripPlaceResourceTest extends TestCase
{
    #[Test]
    public function it_transforms_trip_place_to_array(): void
    {
        $tripPlace = new TripPlace(
            id: 1,
            place: [
                'id' => 100,
                'name' => 'Sydney Opera House',
                'category_slug' => 'landmark',
                'lat' => -33.8568,
                'lon' => 151.2153,
            ],
            status: 'confirmed',
            is_fixed: true,
            day: 2,
            order_index: 1,
            note: 'Visit during the day',
            added_by: 5
        );

        $resource = new TripPlaceResource($tripPlace);
        $result = $resource->toArray(request());

        $this->assertEquals(1, $result['id']);

        $this->assertArrayHasKey('place', $result);
        $this->assertEquals(100, $result['place']['id']);
        $this->assertEquals('Sydney Opera House', $result['place']['name']);
        $this->assertEquals('landmark', $result['place']['category_slug']);
        $this->assertEquals(-33.8568, $result['place']['lat']);
        $this->assertEquals(151.2153, $result['place']['lon']);

        $this->assertEquals('confirmed', $result['status']);
        $this->assertTrue($result['is_fixed']);
        $this->assertEquals(2, $result['day']);
        $this->assertEquals(1, $result['order_index']);
        $this->assertEquals('Visit during the day', $result['note']);
        $this->assertEquals(5, $result['added_by']);
    }

    #[Test]
    public function it_handles_null_values(): void
    {
        $tripPlace = new TripPlace(
            id: 1,
            place: [
                'id' => 100,
                'name' => 'Test Place',
                'category_slug' => 'test',
                'lat' => null,
                'lon' => null,
            ],
            status: 'pending',
            is_fixed: false,
            day: null,
            order_index: null,
            note: null,
            added_by: null
        );

        $resource = new TripPlaceResource($tripPlace);
        $result = $resource->toArray(request());

        $this->assertNull($result['place']['lat']);
        $this->assertNull($result['place']['lon']);
        $this->assertNull($result['day']);
        $this->assertNull($result['order_index']);
        $this->assertNull($result['note']);
        $this->assertNull($result['added_by']);
    }

    #[Test]
    public function it_returns_correct_structure(): void
    {
        $tripPlace = new TripPlace(
            id: 1,
            place: [
                'id' => 100,
                'name' => 'Test Place',
                'category_slug' => 'test',
                'lat' => 0.0,
                'lon' => 0.0,
            ],
            status: 'confirmed',
            is_fixed: false,
            day: 1,
            order_index: 0,
            note: '',
            added_by: 1
        );

        $resource = new TripPlaceResource($tripPlace);
        $result = $resource->toArray(request());

        $expectedKeys = ['id', 'place', 'status', 'is_fixed', 'day', 'order_index', 'note', 'added_by'];
        $this->assertEquals($expectedKeys, array_keys($result));

        $placeKeys = ['id', 'name', 'category_slug', 'lat', 'lon'];
        $this->assertEquals($placeKeys, array_keys($result['place']));
    }
}
