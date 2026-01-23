<?php

namespace Tests\Unit\Services;

use App\DTO\Trip\Invite;
use App\Models\Trip;
use App\Models\User;
use App\Services\Activity\ActivityLogger;
use App\Services\TripService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\TestCase;

class TripServiceTest extends TestCase
{
    use RefreshDatabase;

    private TripService $service;
    private MockObject|ActivityLogger $activityLogger;
    private User $owner;
    private User $member;
    private User $editor;
    private Trip $trip;

    protected function setUp(): void
    {
        parent::setUp();

        $this->activityLogger = $this->createMock(ActivityLogger::class);
        $this->service = new TripService($this->activityLogger);

        $this->owner = User::factory()->create();
        $this->member = User::factory()->create();
        $this->editor = User::factory()->create();
        $this->trip = Trip::factory()->create(['owner_id' => $this->owner->id]);
    }

    #[Test]
    public function it_lists_user_trips(): void
    {
        // Create additional trips
        $trip2 = Trip::factory()->create(['owner_id' => $this->owner->id]);
        $otherTrip = Trip::factory()->create(['owner_id' => $this->member->id]);

        // Add user as member to another trip
        $otherTrip->members()->attach($this->owner->id, [
            'role' => 'member',
            'status' => 'accepted'
        ]);

        $result = $this->service->list($this->owner);

        $this->assertCount(3, $result->items());
        $items = $result->items();
        $this->assertTrue(collect($items)->pluck('id')->contains($this->trip->id));
        $this->assertTrue(collect($items)->pluck('id')->contains($trip2->id));
        $this->assertTrue(collect($items)->pluck('id')->contains($otherTrip->id));
    }

    #[Test]
    public function it_creates_trip_with_activity_log(): void
    {
        $data = [
            'name' => 'Test Trip',
            'description' => 'Test Description',
            'start_date' => '2024-01-01',
            'end_date' => '2024-01-05'
        ];

        $this->activityLogger
            ->expects($this->once())
            ->method('add')
            ->with(
                $this->owner,
                'trip.created',
                $this->anything(),
                $this->callback(fn($details) =>
                    $details['trip_id'] &&
                    $details['name'] === 'Test Trip' &&
                    $details['owner_id'] === $this->owner->id
                )
            );

        $trip = $this->service->create($data, $this->owner);

        $this->assertEquals('Test Trip', $trip->name);
        $this->assertEquals('Test Description', $trip->description);
        $this->assertEquals($this->owner->id, $trip->owner_id);
    }

    #[Test]
    public function it_updates_trip(): void
    {
        $data = ['name' => 'Updated Trip'];

        $originalName = $this->trip->name;
        $updated = $this->service->update($data, $this->trip);

        $this->assertEquals('Updated Trip', $updated->name);
        $this->assertNotEquals($originalName, $updated->name);
    }

    #[Test]
    public function it_deletes_trip(): void
    {
        $this->service->delete($this->trip);

        $this->assertModelMissing($this->trip);
    }

    #[Test]
    public function it_updates_start_location(): void
    {
        $data = [
            'start_latitude' => 52.2297,
            'start_longitude' => 21.0122
        ];

        $updated = $this->service->updateStartLocation($data, $this->trip);

        $this->assertEquals(52.2297, $updated->start_latitude);
        $this->assertEquals(21.0122, $updated->start_longitude);
    }

    #[Test]
    public function it_invites_user_to_trip(): void
    {
        $invitee = User::factory()->create(['email' => 'test@example.com']);
        $data = ['email' => 'test@example.com', 'role' => 'editor'];

        $this->activityLogger
            ->expects($this->once())
            ->method('add')
            ->with(
                $this->owner,
                'trip.member_invited',
                $this->trip,
                $this->callback(fn($details) =>
                    $details['trip_id'] === $this->trip->id &&
                    $details['user_id'] === $invitee->id &&
                    $details['role'] === 'editor'
                )
            );

        $invite = $this->service->inviteUser($this->trip, $this->owner, $data);

        $this->assertInstanceOf(Invite::class, $invite);
        $this->assertEquals($this->trip->id, $invite->trip_id); // Check trip_id instead of owner.id
        $this->assertEquals('editor', $invite->role);
        $this->assertEquals('pending', $invite->status);

        $this->assertDatabaseHas('trip_user', [
            'trip_id' => $this->trip->id,
            'user_id' => $invitee->id,
            'role' => 'editor',
            'status' => 'pending'
        ]);
    }

    #[Test]
    public function it_prevents_owner_self_invitation(): void
    {
        $data = ['email' => $this->owner->email];

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Owner cannot invite themselves.');

        $this->service->inviteUser($this->trip, $this->owner, $data);
    }

    #[Test]
    public function it_prevents_duplicate_invitation(): void
    {
        $invitee = User::factory()->create();
        $data = ['email' => $invitee->email];

        // First invitation
        $this->service->inviteUser($this->trip, $this->owner, $data);

        // Second invitation should fail
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('This user is already invited.');

        $this->service->inviteUser($this->trip, $this->owner, $data);
    }

    #[Test]
    public function it_accepts_invite(): void
    {
        $invitee = User::factory()->create();
        $this->trip->members()->attach($invitee->id, [
            'role' => 'member',
            'status' => 'pending'
        ]);

        $this->activityLogger
            ->expects($this->once())
            ->method('add')
            ->with(
                $invitee,
                'trip.member_added',
                $this->trip,
                $this->callback(fn($details) =>
                    $details['trip_id'] === $this->trip->id &&
                    $details['user_id'] === $invitee->id
                )
            );

        $this->service->acceptInvite($this->trip, $invitee);

        $this->assertDatabaseHas('trip_user', [
            'trip_id' => $this->trip->id,
            'user_id' => $invitee->id,
            'status' => 'accepted'
        ]);
    }

    #[Test]
    public function it_declines_invite(): void
    {
        $invitee = User::factory()->create();
        $this->trip->members()->attach($invitee->id, [
            'role' => 'member',
            'status' => 'pending'
        ]);

        $this->service->declineInvite($this->trip, $invitee);

        $this->assertDatabaseHas('trip_user', [
            'trip_id' => $this->trip->id,
            'user_id' => $invitee->id,
            'status' => 'declined'
        ]);
    }

    #[Test]
    public function it_lists_trip_members(): void
    {
        // Add accepted member
        $this->trip->members()->attach($this->member->id, [
            'role' => 'member',
            'status' => 'accepted'
        ]);

        // Add pending member
        $pending = User::factory()->create();
        $this->trip->members()->attach($pending->id, [
            'role' => 'editor',
            'status' => 'pending'
        ]);

        $members = $this->service->listMembers($this->trip);

        $this->assertCount(3, $members); // Owner + accepted member + pending member (pending is included)
        $this->assertTrue($members->contains('id', $this->owner->id));
        $this->assertTrue($members->contains('id', $this->member->id));
        $this->assertTrue($members->contains('id', $pending->id));

        $ownerMember = $members->firstWhere('id', $this->owner->id);
        $this->assertTrue($ownerMember->is_owner);
        $this->assertEquals('owner', $ownerMember->pivot->role);
    }

    #[Test]
    public function it_updates_member_role(): void
    {
        $this->trip->members()->attach($this->member->id, [
            'role' => 'member',
            'status' => 'accepted'
        ]);

        $this->activityLogger
            ->expects($this->once())
            ->method('add')
            ->with(
                $this->owner,
                'trip.member_role_updated',
                $this->trip,
                $this->callback(fn($details) =>
                    $details['trip_id'] === $this->trip->id &&
                    $details['user_id'] === $this->member->id &&
                    $details['before'] === 'member' &&
                    $details['after'] === 'editor'
                )
            );

        $this->service->updateMemberRole($this->trip, $this->member, 'editor', $this->owner);

        $this->assertDatabaseHas('trip_user', [
            'trip_id' => $this->trip->id,
            'user_id' => $this->member->id,
            'role' => 'editor'
        ]);
    }

    #[Test]
    public function it_prevents_role_update_for_owner(): void
    {
        $this->expectException(AuthorizationException::class);
        $this->expectExceptionMessage('Forbidden.');

        $this->service->updateMemberRole($this->trip, $this->owner, 'member', $this->owner);
    }

    #[Test]
    public function it_removes_member(): void
    {
        $this->trip->members()->attach($this->member->id, [
            'role' => 'member',
            'status' => 'accepted'
        ]);

        $this->activityLogger
            ->expects($this->once())
            ->method('add')
            ->with(
                $this->owner,
                'trip.member_removed',
                $this->trip,
                $this->callback(fn($details) =>
                    $details['trip_id'] === $this->trip->id &&
                    $details['user_id'] === $this->member->id &&
                    $details['removed_by'] === $this->owner->id
                )
            );

        $this->service->removeMember($this->trip, $this->member, $this->owner);

        $this->assertDatabaseMissing('trip_user', [
            'trip_id' => $this->trip->id,
            'user_id' => $this->member->id
        ]);
    }

    #[Test]
    public function it_lists_user_invites(): void
    {
        $testUser = User::factory()->create();
        
        $trip1 = Trip::factory()->create(['owner_id' => $this->member->id]);
        $trip2 = Trip::factory()->create(['owner_id' => $this->editor->id]);

        $trip1->members()->attach($testUser->id, [
            'role' => 'member',
            'status' => 'pending'
        ]);

        $trip2->members()->attach($testUser->id, [
            'role' => 'editor',
            'status' => 'pending'
        ]);

        $trip3 = Trip::factory()->create(['owner_id' => $this->owner->id]);
        $trip3->members()->attach($testUser->id, [
            'role' => 'member',
            'status' => 'accepted'
        ]);

        $invites = $this->service->listUserInvites($testUser);

        $this->assertCount(2, $invites);
        $this->assertTrue(collect($invites)->pluck('trip_id')->contains($trip1->id));
        $this->assertTrue(collect($invites)->pluck('trip_id')->contains($trip2->id));
        $this->assertFalse(collect($invites)->pluck('trip_id')->contains($trip3->id));
    }

    #[Test]
    public function it_lists_sent_invites(): void
    {
        $invitee1 = User::factory()->create();
        $invitee2 = User::factory()->create();

        $this->trip->members()->attach($invitee1->id, [
            'role' => 'member',
            'status' => 'pending'
        ]);

        $this->trip->members()->attach($invitee2->id, [
            'role' => 'editor',
            'status' => 'pending'
        ]);

        // Add accepted member (should not appear in sent invites)
        $acceptedMember = User::factory()->create();
        $this->trip->members()->attach($acceptedMember->id, [
            'role' => 'member',
            'status' => 'accepted'
        ]);

        $invites = $this->service->listSentInvites($this->owner);

        $this->assertCount(2, $invites);
        $this->assertTrue(collect($invites)->pluck('trip_id')->contains($this->trip->id));
        $this->assertTrue(collect($invites)->pluck('owner.id')->contains($this->owner->id));
        $this->assertFalse(collect($invites)->pluck('owner.id')->contains($acceptedMember->id));
    }
}
