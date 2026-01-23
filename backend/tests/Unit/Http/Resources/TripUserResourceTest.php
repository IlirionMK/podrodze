<?php

namespace Tests\Unit\Http\Resources;

use App\Http\Resources\TripUserResource;
use App\Models\User;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TripUserResourceTest extends TestCase
{
    #[Test]
    public function it_transforms_trip_user_to_array_with_pivot(): void
    {
        $user = User::factory()->make([
            'id' => 15,
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
        ]);

        // Create a mock pivot object
        $pivot = new \stdClass();
        $pivot->role = 'member';
        $pivot->status = 'accepted';
        $user->setRelation('pivot', $pivot);

        $resource = new TripUserResource($user);
        $result = $resource->toArray(request());

        $this->assertEquals(15, $result['id']);
        $this->assertEquals('Jane Doe', $result['name']);
        $this->assertEquals('jane@example.com', $result['email']);
        $this->assertFalse($result['is_owner']);
        $this->assertEquals('member', $result['role']);
        $this->assertEquals('accepted', $result['status']);

        $this->assertIsArray($result['pivot']);
        $this->assertEquals('member', $result['pivot']['role']);
        $this->assertEquals('accepted', $result['pivot']['status']);
    }

    #[Test]
    public function it_identifies_owner_from_pivot_role(): void
    {
        $user = User::factory()->make([
            'id' => 1,
            'name' => 'John Owner',
            'email' => 'owner@example.com',
        ]);

        $pivot = new \stdClass();
        $pivot->role = 'owner';
        $pivot->status = 'accepted';
        $user->setRelation('pivot', $pivot);

        $resource = new TripUserResource($user);
        $result = $resource->toArray(request());

        $this->assertTrue($result['is_owner']);
        $this->assertEquals('owner', $result['role']);
    }

    #[Test]
    public function it_handles_user_without_pivot(): void
    {
        $user = User::factory()->make([
            'id' => 20,
            'name' => 'Bob Smith',
            'email' => 'bob@example.com',
        ]);

        $resource = new TripUserResource($user);
        $result = $resource->toArray(request());

        $this->assertEquals(20, $result['id']);
        $this->assertEquals('Bob Smith', $result['name']);
        $this->assertEquals('bob@example.com', $result['email']);
        $this->assertFalse($result['is_owner']);
        $this->assertEquals('member', $result['role']); // default
        $this->assertEquals('accepted', $result['status']); // default
        $this->assertNull($result['pivot']);
    }

    #[Test]
    public function it_respects_is_owner_property_when_set(): void
    {
        $user = User::factory()->make([
            'id' => 10,
            'name' => 'Alice Johnson',
            'email' => 'alice@example.com',
        ]);
        $user->is_owner = true; // Set the property directly

        $pivot = new \stdClass();
        $pivot->role = 'member'; // Different from is_owner
        $pivot->status = 'pending';
        $user->setRelation('pivot', $pivot);

        $resource = new TripUserResource($user);
        $result = $resource->toArray(request());

        $this->assertTrue($result['is_owner']); // Should use the property, not pivot
        $this->assertEquals('member', $result['role']);
        $this->assertEquals('pending', $result['status']);
    }

    #[Test]
    public function it_handles_different_pivot_statuses(): void
    {
        $user = User::factory()->make([
            'id' => 25,
            'name' => 'Charlie Brown',
            'email' => 'charlie@example.com',
        ]);

        $pivot = new \stdClass();
        $pivot->role = 'member';
        $pivot->status = 'pending';
        $user->setRelation('pivot', $pivot);

        $resource = new TripUserResource($user);
        $result = $resource->toArray(request());

        $this->assertEquals('pending', $result['status']);
        $this->assertEquals('pending', $result['pivot']['status']);
    }

    #[Test]
    public function it_handles_null_pivot_properties(): void
    {
        $user = User::factory()->make([
            'id' => 30,
            'name' => 'Diana Prince',
            'email' => 'diana@example.com',
        ]);

        $pivot = new \stdClass();
        $pivot->role = null;
        $pivot->status = null;
        $user->setRelation('pivot', $pivot);

        $resource = new TripUserResource($user);
        $result = $resource->toArray(request());

        $this->assertFalse($result['is_owner']);
        $this->assertEquals('member', $result['role']);
        $this->assertEquals('accepted', $result['status']);
        $this->assertNull($result['pivot']['role']);
        $this->assertNull($result['pivot']['status']);
    }

    #[Test]
    public function it_returns_correct_structure(): void
    {
        $user = User::factory()->make([
            'id' => 1,
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $resource = new TripUserResource($user);
        $result = $resource->toArray(request());

        $expectedKeys = ['id', 'name', 'email', 'is_owner', 'role', 'status', 'pivot'];
        $this->assertEquals($expectedKeys, array_keys($result));
    }

    #[Test]
    public function it_handles_complex_user_data(): void
    {
        $user = User::factory()->make([
            'id' => 999,
            'name' => 'Complex User Name',
            'email' => 'complex.user+tag@example-domain.co.uk',
        ]);

        $pivot = new \stdClass();
        $pivot->role = 'admin';
        $pivot->status = 'invited';
        $user->setRelation('pivot', $pivot);

        $resource = new TripUserResource($user);
        $result = $resource->toArray(request());

        $this->assertEquals(999, $result['id']);
        $this->assertEquals('Complex User Name', $result['name']);
        $this->assertEquals('complex.user+tag@example-domain.co.uk', $result['email']);
        $this->assertFalse($result['is_owner']);
        $this->assertEquals('admin', $result['role']);
        $this->assertEquals('invited', $result['status']);
        $this->assertEquals('admin', $result['pivot']['role']);
        $this->assertEquals('invited', $result['pivot']['status']);
    }
}
