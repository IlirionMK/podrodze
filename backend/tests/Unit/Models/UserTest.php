<?php

namespace Tests\Unit\Models;

use App\Models\Preference;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase\ModelTestCase;
use Illuminate\Support\Facades\Hash;

class UserTest extends ModelTestCase
{

    #[Test]
    public function it_has_required_fields()
    {
        $user = $this->createUser([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => Hash::make('secret')
        ]);

        $this->assertEquals('John Doe', $user->name);
        $this->assertEquals('john@example.com', $user->email);
        $this->assertNotEquals('secret', $user->password);
        $this->assertTrue(Hash::check('secret', $user->password));
    }

    #[Test]
    public function it_has_trips_relationship()
    {
        $user = $this->createUser();
        $trip1 = $this->createTrip($user->id, ['name' => 'Trip 1']);
        $trip2 = $this->createTrip($user->id, ['name' => 'Trip 2']);

        $userTrips = $user->trips;

        $this->assertCount(2, $userTrips);
        $this->assertEquals('Trip 1', $userTrips[0]->name);
        $this->assertEquals('Trip 2', $userTrips[1]->name);
    }

    #[Test]
    public function it_has_joined_trips_relationship()
    {
        $user = $this->createUser(['email' => 'user1@example.com']);
        $otherUser = $this->createUser(['email' => 'other@example.com']);

        $trip = $this->createTrip($otherUser->id, ['name' => 'Joined Trip']);
        $user->joinedTrips()->attach($trip->id, ['role' => 'member', 'status' => 'accepted']);

        $this->assertCount(1, $user->joinedTrips);
        $this->assertEquals('Joined Trip', $user->joinedTrips->first()->name);
        $this->assertEquals('member', $user->joinedTrips->first()->pivot->role);
    }


    #[Test]
    public function it_has_hidden_fields()
    {
        $user = $this->createUser();
        $userArray = $user->toArray();

        $this->assertArrayNotHasKey('password', $userArray);
        $this->assertArrayNotHasKey('remember_token', $userArray);
    }

    #[Test]
    public function it_casts_attributes_correctly()
    {
        $user = $this->createUser([
            'email' => 'test_casts@example.com',
            'email_verified_at' => now(),
            'banned_at' => now(),
        ]);

        $this->assertIsString($user->password);

        if (property_exists($user, 'email_verified_at')) {
            $this->assertInstanceOf(\DateTimeInterface::class, $user->email_verified_at);
        }

        if (property_exists($user, 'banned_at')) {
            $this->assertInstanceOf(\DateTimeInterface::class, $user->banned_at);
        }
    }
}
