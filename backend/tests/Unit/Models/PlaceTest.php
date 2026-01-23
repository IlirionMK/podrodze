<?php

namespace Tests\Unit\Models;

use App\Models\Place;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase\ModelTestCase;

class PlaceTest extends ModelTestCase
{

    #[Test]
    public function it_has_required_fields()
    {
        $place = $this->createPlace([
            'name' => 'Eiffel Tower',
            'google_place_id' => 'eiffel_tower_123',
            'rating' => 4.7,
        ]);

        $this->assertEquals('Eiffel Tower', $place->name);
        $this->assertEquals('eiffel_tower_123', $place->google_place_id);
        $this->assertEquals(4.7, $place->rating);
    }

    #[Test]
    public function it_can_have_trips_relation()
    {
        $place = $this->createPlace();
        $trip = $this->createTrip();

        $place->trips()->attach($trip->id, [
            'order_index' => 1,
            'status' => 'pending',
            'note' => 'Test note'
        ]);

        $this->assertCount(1, $place->trips);
        $this->assertEquals($trip->id, $place->trips->first()->id);
        $this->assertEquals('Test note', $place->trips->first()->pivot->note);
    }

    #[Test]
    public function it_casts_attributes_correctly()
    {
        $place = $this->createPlace([
            'meta' => ['key' => 'value'],
            'opening_hours' => ['monday' => '9:00-17:00'],
            'rating' => '4.5',
        ]);

        $this->assertIsArray($place->meta);
        $this->assertEquals('value', $place->meta['key']);
        $this->assertIsArray($place->opening_hours);
        $this->assertEquals('9:00-17:00', $place->opening_hours['monday']);
        $this->assertIsFloat($place->rating);
        $this->assertEquals(4.5, $place->rating);
    }

    #[Test]
    public function it_has_fillable_fields()
    {
        $fillable = [
            'name',
            'google_place_id',
            'category_slug',
            'rating',
            'meta',
            'opening_hours',
            'location',
        ];

        $place = new Place();
        $this->assertEquals($fillable, $place->getFillable());
    }
}
