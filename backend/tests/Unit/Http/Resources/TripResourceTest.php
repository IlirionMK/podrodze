<?php

namespace Tests\Unit\Http\Resources;

use App\Http\Resources\TripResource;
use App\Models\Trip;
use App\Models\User;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseMigrations;

class TripResourceTest extends TestCase
{
    use DatabaseMigrations;
    
    #[Test]
    public function it_transforms_trip_to_array(): void
    {
        $owner = User::factory()->create(['id' => 5]);
        $trip = Trip::factory()->create([
            'id' => 1,
            'name' => 'Hike to Altai Mountains',
            'description' => 'A challenging mountain hiking expedition',
            'start_date' => '2025-06-10 00:00:00',
            'end_date' => '2025-06-17 00:00:00',
            'start_latitude' => 55.7558,
            'start_longitude' => 37.6176,
            'owner_id' => $owner->id,
            'created_at' => '2025-05-01 12:00:00',
            'updated_at' => '2025-05-01 12:00:00',
        ]);

        $resource = new TripResource($trip);
        $result = $resource->toArray(request());

        $this->assertEquals(1, $result['id']);
        $this->assertEquals('Hike to Altai Mountains', $result['name']);
        $this->assertEquals('A challenging mountain hiking expedition', $result['description']);
        $this->assertEquals('2025-06-10T00:00:00.000000Z', $result['start_date']);
        $this->assertEquals('2025-06-17T00:00:00.000000Z', $result['end_date']);
        $this->assertEquals(55.7558, $result['start_latitude']);
        $this->assertEquals(37.6176, $result['start_longitude']);
        $this->assertEquals(5, $result['owner_id']);
        $this->assertEquals('2025-05-01T12:00:00.000000Z', $result['created_at']);
        $this->assertEquals('2025-05-01T12:00:00.000000Z', $result['updated_at']);
    }

    #[Test]
    public function it_includes_owner_when_loaded(): void
    {
        $owner = User::factory()->create(['name' => 'John Doe']);
        $trip = Trip::factory()->create(['owner_id' => $owner->id]);
        $trip->load('owner');

        $resource = new TripResource($trip);
        $result = $resource->toArray(request());

        $this->assertArrayHasKey('owner', $result);
        $this->assertEquals($owner->id, $result['owner']['id']);
        $this->assertEquals('John Doe', $result['owner']['name']);
    }

    #[Test]
    public function it_excludes_owner_when_not_loaded(): void
    {
        $owner = User::factory()->create();
        $trip = Trip::factory()->create(['owner_id' => $owner->id]);

        $resource = new TripResource($trip);
        $result = $resource->toArray(request());

        $this->assertArrayHasKey('owner', $result);
        $this->assertInstanceOf(\App\Http\Resources\UserMiniResource::class, $result['owner']);
    }

    #[Test]
    public function it_includes_members_when_loaded(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $trip = Trip::factory()->create(['owner_id' => $owner->id]);

        $trip->members()->attach($member->id);
        $trip->load('members');

        $resource = new TripResource($trip);
        $result = $resource->toArray(request());

        $this->assertArrayHasKey('members', $result);
        $this->assertInstanceOf(\Illuminate\Http\Resources\Json\AnonymousResourceCollection::class, $result['members']);
        $this->assertCount(2, $result['members']); // owner + member
    }

    #[Test]
    public function it_excludes_members_when_not_loaded(): void
    {
        $owner = User::factory()->create();
        $trip = Trip::factory()->create(['owner_id' => $owner->id]);

        $resource = new TripResource($trip);
        $result = $resource->toArray(request());

        $this->assertArrayHasKey('members', $result);
        $this->assertInstanceOf(\Illuminate\Http\Resources\Json\AnonymousResourceCollection::class, $result['members']);
    }

    #[Test]
    public function it_handles_null_dates(): void
    {
        $owner = User::factory()->create();
        $trip = Trip::factory()->create([
            'owner_id' => $owner->id,
            'start_date' => null,
            'end_date' => null,
        ]);

        $resource = new TripResource($trip);
        $result = $resource->toArray(request());

        $this->assertNull($result['start_date']);
        $this->assertNull($result['end_date']);
    }

    #[Test]
    public function it_handles_null_coordinates(): void
    {
        $owner = User::factory()->create();
        $trip = Trip::factory()->create([
            'owner_id' => $owner->id,
            'start_latitude' => null,
            'start_longitude' => null,
        ]);

        $resource = new TripResource($trip);
        $result = $resource->toArray(request());

        $this->assertNull($result['start_latitude']);
        $this->assertNull($result['start_longitude']);
    }

    #[Test]
    public function it_returns_correct_structure(): void
    {
        $owner = User::factory()->create();
        $trip = Trip::factory()->create(['owner_id' => $owner->id]);

        $resource = new TripResource($trip);
        $result = $resource->toArray(request());

        $expectedKeys = [
            'id', 'name', 'description', 'start_date', 'end_date',
            'start_latitude', 'start_longitude', 'owner_id',
            'owner', 'members', 'created_at', 'updated_at'
        ];
        $this->assertEquals($expectedKeys, array_keys($result));
    }
}
