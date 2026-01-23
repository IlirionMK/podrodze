<?php

namespace Tests\Unit\Models;

use App\Models\Place;
use App\Models\Trip;
use App\Models\User;
use Tests\TestCase\ModelTestCase;
use PHPUnit\Framework\Attributes\Test;

class TripTest extends ModelTestCase
{
    #[Test]
    public function it_has_required_fields()
    {
        $user = $this->createUser();
        $trip = $this->createTrip(['owner_id' => $user->id, 
            'name' => 'Summer Vacation',
            'description' => 'Trip to the mountains',
        ]);

        $this->assertEquals('Summer Vacation', $trip->name);
        $this->assertEquals('Trip to the mountains', $trip->description);
        $this->assertEquals($user->id, $trip->owner_id);
    }

    #[Test]
    public function it_can_add_participant()
    {
        $owner = $this->createUser(['name' => 'Trip Owner']);
        $user = $this->createUser(['name' => 'New Participant']);
        $trip = $this->createTrip($owner->id);

        $trip->members()->attach($user->id, [
            'role' => 'member',
            'status' => 'accepted'
        ]);

        $this->assertTrue($trip->members->contains($user));
        $this->assertEquals('member', $trip->members->find($user->id)->pivot->role);
    }

    #[Test]
    public function it_can_remove_participant()
    {
        $owner = $this->createUser(['name' => 'Trip Owner']);
        $user = $this->createUser(['name' => 'Participant to Remove']);
        $trip = $this->createTrip($owner->id);

        $trip->members()->attach($user->id, [
            'role' => 'member',
            'status' => 'accepted'
        ]);

        $this->assertTrue($trip->members->contains($user));

        $trip->members()->detach($user->id);

        $trip->refresh();

        $this->assertFalse($trip->members->contains($user));
    }

    #[Test]
    public function it_validates_dates()
    {
        $this->expectException('Carbon\Exceptions\InvalidFormatException');

        $user = $this->createUser();

        $trip = new Trip([
            'name' => 'Test Trip',
            'description' => 'Test Description',
            'start_date' => 'invalid-date',
            'end_date' => 'invalid-date',
            'owner_id' => $user->id
        ]);

        $trip->save();
    }

    #[Test]
    public function it_has_places_relationship()
    {
        $user = $this->createUser();
        $trip = $this->createTrip(['owner_id' => $user->id]);
        $place = $this->createPlace(['name' => 'Test Place']);

        $trip->places()->attach($place->id, [
            'order_index' => 1,
            'status' => 'planned',
            'note' => 'Test note'
        ]);

        $this->assertCount(1, $trip->places);
        $this->assertEquals('Test Place', $trip->places->first()->name);
        $this->assertEquals('Test note', $trip->places->first()->pivot->note);
    }
}
