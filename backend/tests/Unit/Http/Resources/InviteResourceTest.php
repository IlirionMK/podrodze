<?php

namespace Tests\Unit\Http\Resources;

use App\DTO\Trip\Invite;
use App\Http\Resources\InviteResource;
use App\Models\User;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class InviteResourceTest extends TestCase
{
    #[Test]
    public function it_transforms_invite_to_array(): void
    {
        $owner = User::factory()->create([
            'id' => 10,
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);

        $invite = new Invite(
            trip_id: 10,
            name: 'Summer Roadtrip',
            start_date: '2025-07-01T00:00:00.000000Z',
            end_date: '2025-07-15T00:00:00.000000Z',
            role: 'editor',
            status: 'pending',
            owner: $owner
        );

        $resource = new InviteResource($invite);
        $result = $resource->toArray(request());

        $this->assertEquals(10, $result['trip_id']);
        $this->assertEquals('Summer Roadtrip', $result['name']);
        $this->assertEquals('2025-07-01T00:00:00.000000Z', $result['start_date']);
        $this->assertEquals('2025-07-15T00:00:00.000000Z', $result['end_date']);
        $this->assertEquals('editor', $result['role']);
        $this->assertEquals('pending', $result['status']);

        $this->assertArrayHasKey('owner', $result);
        $this->assertEquals(10, $result['owner']['id']);
        $this->assertEquals('John Doe', $result['owner']['name']);
        $this->assertEquals('john@example.com', $result['owner']['email']);
    }

    #[Test]
    public function it_handles_null_owner(): void
    {
        $owner = User::factory()->create([
            'id' => 99,
            'name' => 'Mock Owner',
            'email' => 'mock@example.com',
        ]);

        $invite = new Invite(
            trip_id: 10,
            name: 'Summer Roadtrip',
            start_date: null,
            end_date: null,
            role: 'editor',
            status: 'pending',
            owner: $owner
        );

        $resource = new InviteResource($invite);
        $result = $resource->toArray(request());

        $this->assertNotNull($result['owner']);
        $this->assertEquals(99, $result['owner']['id']);
        $this->assertEquals('Mock Owner', $result['owner']['name']);
        $this->assertEquals('mock@example.com', $result['owner']['email']);
    }

    #[Test]
    public function it_handles_null_dates(): void
    {
        $owner = User::factory()->create();

        $invite = new Invite(
            trip_id: 10,
            name: 'Summer Roadtrip',
            start_date: null,
            end_date: null,
            role: 'editor',
            status: 'pending',
            owner: $owner
        );

        $resource = new InviteResource($invite);
        $result = $resource->toArray(request());

        $this->assertNull($result['start_date']);
        $this->assertNull($result['end_date']);
    }

    #[Test]
    public function it_returns_correct_structure(): void
    {
        $owner = User::factory()->create();

        $invite = new Invite(
            trip_id: 10,
            name: 'Summer Roadtrip',
            start_date: '2025-07-01T00:00:00.000000Z',
            end_date: '2025-07-15T00:00:00.000000Z',
            role: 'editor',
            status: 'pending',
            owner: $owner
        );

        $resource = new InviteResource($invite);
        $result = $resource->toArray(request());

        $expectedKeys = ['trip_id', 'name', 'start_date', 'end_date', 'role', 'status', 'owner'];
        $this->assertEquals($expectedKeys, array_keys($result));
    }
}
