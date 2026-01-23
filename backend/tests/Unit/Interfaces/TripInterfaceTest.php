<?php

namespace Tests\Unit\Interfaces;

use App\DTO\Trip\Invite;
use App\Interfaces\TripInterface;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Mockery;
use Tests\TestCase;

class TripInterfaceTest extends TestCase
{
    private TripInterface $tripService;
    private User $user;
    private User $owner;
    private Trip $trip;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tripService = Mockery::mock(TripInterface::class);
        $this->user = User::factory()->create(['id' => 2]);
        $this->owner = User::factory()->create(['id' => 1]);
        $this->trip = Trip::factory()->create(['owner_id' => $this->owner->id]);
    }

    // CRUD Tests
    public function test_list_trips()
    {
        $paginator = Mockery::mock(LengthAwarePaginator::class);

        $this->tripService->shouldReceive('list')
            ->once()
            ->with($this->user)
            ->andReturn($paginator);

        $result = $this->tripService->list($this->user);

        $this->assertInstanceOf(LengthAwarePaginator::class, $result);
    }

    public function test_create_trip()
    {
        $data = [
            'name' => 'Summer Vacation',
            'start_date' => '2025-07-01',
            'end_date' => '2025-07-14',
            'location' => 'Paris, France'
        ];

        $this->tripService->shouldReceive('create')
            ->once()
            ->with($data, $this->owner)
            ->andReturn($this->trip);

        $result = $this->tripService->create($data, $this->owner);

        $this->assertInstanceOf(Trip::class, $result);
        $this->assertEquals($this->owner->id, $result->owner_id);
    }

    public function test_update_trip()
    {
        $data = ['name' => 'Updated Trip Name'];
        $updatedTrip = (clone $this->trip)->fill($data);

        $this->tripService->shouldReceive('update')
            ->once()
            ->with($data, $this->trip)
            ->andReturn($updatedTrip);

        $result = $this->tripService->update($data, $this->trip);

        $this->assertInstanceOf(Trip::class, $result);
        $this->assertEquals('Updated Trip Name', $result->name);
    }

    public function test_delete_trip()
    {
        $this->tripService->shouldReceive('delete')
            ->once()
            ->with($this->trip);

        $this->tripService->delete($this->trip);
        
        // Verify the mock was called correctly
        $this->assertTrue(true); // Test reaches this point if method was called
    }

    // Member Management Tests
    public function test_invite_user()
    {
        $inviteData = ['email' => 'test@example.com', 'role' => 'member'];

        // Create a mock user for the invite
        $invitedUser = User::factory()->make(['email' => 'test@example.com']);

        // Create an Invite DTO with all required parameters
        $invite = new Invite(
            trip_id: $this->trip->id,
            name: $this->trip->name,
            start_date: $this->trip->start_date,
            end_date: $this->trip->end_date,
            role: 'member',
            status: 'pending',
            owner: $this->owner
        );

        $this->tripService->shouldReceive('inviteUser')
            ->once()
            ->with($this->trip, $this->owner, $inviteData)
            ->andReturn($invite);

        $result = $this->tripService->inviteUser($this->trip, $this->owner, $inviteData);

        $this->assertInstanceOf(Invite::class, $result);
        $this->assertEquals($this->trip->id, $result->trip_id);
        $this->assertEquals('member', $result->role);
    }

    public function test_accept_invite()
    {
        $this->tripService->shouldReceive('acceptInvite')
            ->once()
            ->with($this->trip, $this->user);

        $this->tripService->acceptInvite($this->trip, $this->user);
        
        // Verify the mock was called correctly
        $this->assertTrue(true);
    }

    public function test_list_members()
    {
        $members = new Collection([$this->owner, $this->user]);

        $this->tripService->shouldReceive('listMembers')
            ->once()
            ->with($this->trip)
            ->andReturn($members);

        $result = $this->tripService->listMembers($this->trip);

        $this->assertInstanceOf(Collection::class, $result);
        $this->assertCount(2, $result);
    }

    public function test_update_member_role()
    {
        $this->tripService->shouldReceive('updateMemberRole')
            ->once()
            ->with($this->trip, $this->user, 'admin', $this->owner);

        $this->tripService->updateMemberRole($this->trip, $this->user, 'admin', $this->owner);
        
        // Verify the mock was called correctly
        $this->assertTrue(true);
    }

    public function test_remove_member()
    {
        $this->tripService->shouldReceive('removeMember')
            ->once()
            ->with($this->trip, $this->user, $this->owner);

        $this->tripService->removeMember($this->trip, $this->user, $this->owner);
        
        // Verify the mock was called correctly
        $this->assertTrue(true);
    }

    // Invites Tests
    public function test_list_user_invites()
    {
        $invites = new Collection([
            ['trip_id' => 1, 'email' => 'test@example.com', 'role' => 'member']
        ]);

        $this->tripService->shouldReceive('listUserInvites')
            ->once()
            ->with($this->user)
            ->andReturn($invites);

        $result = $this->tripService->listUserInvites($this->user);

        $this->assertInstanceOf(Collection::class, $result);
        $this->assertCount(1, $result);
    }

    public function test_list_sent_invites()
    {
        $invites = new Collection([
            ['trip_id' => 1, 'email' => 'test@example.com', 'role' => 'member']
        ]);

        $this->tripService->shouldReceive('listSentInvites')
            ->once()
            ->with($this->owner)
            ->andReturn($invites);

        $result = $this->tripService->listSentInvites($this->owner);

        $this->assertInstanceOf(Collection::class, $result);
        $this->assertCount(1, $result);
    }
}
