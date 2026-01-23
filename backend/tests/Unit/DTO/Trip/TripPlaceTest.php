<?php

namespace Tests\Unit\DTO\Trip;

use App\DTO\Trip\TripPlace;
use App\Models\Place;
use PHPUnit\Framework\TestCase;

class TripPlaceTest extends TestCase
{
    public function test_it_creates_trip_place()
    {
        $tripPlace = new TripPlace(
            id: 1,
            place: [
                'id' => 1,
                'name' => 'Test Place',
                'category_slug' => 'restaurant',
                'lat' => 52.2297,
                'lon' => 21.0122
            ],
            status: 'pending',
            is_fixed: false,
            day: 1,
            order_index: 1,
            note: 'Test note',
            added_by: 1
        );

        $this->assertEquals(1, $tripPlace->id);
        $this->assertEquals('Test Place', $tripPlace->place['name']);
        $this->assertEquals('restaurant', $tripPlace->place['category_slug']);
        $this->assertEquals(52.2297, $tripPlace->place['lat']);
        $this->assertEquals(21.0122, $tripPlace->place['lon']);
        $this->assertEquals('pending', $tripPlace->status);
        $this->assertFalse($tripPlace->is_fixed);
        $this->assertEquals(1, $tripPlace->day);
        $this->assertEquals(1, $tripPlace->order_index);
        $this->assertEquals('Test note', $tripPlace->note);
        $this->assertEquals(1, $tripPlace->added_by);
    }

    public function test_it_creates_from_model()
    {
        $place = new class extends Place {
            public $id = 1;
            public $name = 'Test Place';
            public $category_slug = 'restaurant';
            public $lat = 52.2297;
            public $lon = 21.0122;
            public $pivot;

            public function __construct() {
                parent::__construct();
                $this->pivot = (object) [
                    'status' => 'pending',
                    'is_fixed' => false,
                    'day' => 1,
                    'order_index' => 1,
                    'note' => 'Test note',
                    'added_by' => 1
                ];
            }
        };

        // Create DTO from model
        $tripPlace = TripPlace::fromModel($place);

        // Assert
        $this->assertEquals(1, $tripPlace->id);
        $this->assertEquals('Test Place', $tripPlace->place['name']);
        $this->assertEquals('restaurant', $tripPlace->place['category_slug']);
        $this->assertEquals(52.2297, $tripPlace->place['lat']);
        $this->assertEquals(21.0122, $tripPlace->place['lon']);
        $this->assertEquals('pending', $tripPlace->status);
        $this->assertFalse($tripPlace->is_fixed);
        $this->assertEquals(1, $tripPlace->day);
        $this->assertEquals(1, $tripPlace->order_index);
        $this->assertEquals('Test note', $tripPlace->note);
        $this->assertEquals(1, $tripPlace->added_by);
    }
}
