<?php

namespace Tests\Feature\TripManagement;

use App\Models\Trip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * Tests for trip member management.
 *
 * This class verifies that:
 * - Members can be added to and removed from trips
 * - Role-based permissions are enforced
 * - Member status changes are handled correctly
 * - Editor permissions work as expected
 * - Owner privileges are maintained
 */
#[Group('members')]
#[Group('trip')]
class TripMemberManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;
    protected Trip $trip;
    protected User $member;
    protected User $editor;
    protected User $otherUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->create();
        $this->trip = Trip::factory()->create(['owner_id' => $this->owner->id]);
        $this->member = User::factory()->create();
        $this->editor = User::factory()->create();
        $this->otherUser = User::factory()->create();

        $this->trip->members()->attach($this->editor->id, [
            'status' => 'accepted',
            'role' => 'editor'
        ]);
    }

    public function test_owner_can_list_trip_members(): void
    {
        $this->trip->members()->attach($this->member->id, [
            'status' => 'accepted',
            'role' => 'member'
        ]);

        $response = $this->actingAs($this->owner)
            ->getJson("/api/v1/trips/{$this->trip->id}/members");

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data');
    }

    public function test_member_can_view_trip_details(): void
    {
        $this->trip->members()->attach($this->member->id, [
            'status' => 'accepted',
            'role' => 'member'
        ]);

        $response = $this->actingAs($this->member)
            ->getJson("/api/v1/trips/{$this->trip->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $this->trip->id);
    }

    public function test_editor_cannot_invite_members_with_invalid_role(): void
    {
        $response = $this->actingAs($this->editor)
            ->postJson("/api/v1/trips/{$this->trip->id}/members/invite", [
                'email' => $this->otherUser->email,
                'role' => 'invalid_role'
            ]);
        $response->assertStatus(422);
    }

    public function test_owner_can_delete_trip(): void
    {
        $response = $this->actingAs($this->owner)
            ->deleteJson("/api/v1/trips/{$this->trip->id}");
        $response->assertStatus(200);
    }

    public function test_owner_can_remove_member_from_trip(): void
    {
        $this->trip = Trip::factory()->create(['owner_id' => $this->owner->id]);
        $response = $this->actingAs($this->owner)
            ->deleteJson("/api/v1/trips/{$this->trip->id}/members/{$this->member->id}");
        $response->assertStatus(200);
    }

    public function test_owner_can_remove_editor_from_trip(): void
    {
        $this->trip = Trip::factory()->create(['owner_id' => $this->owner->id]);
        $response = $this->actingAs($this->owner)
            ->deleteJson("/api/v1/trips/{$this->trip->id}/members/{$this->editor->id}");
        $response->assertStatus(200);
    }

    public function test_owner_can_invite_member_to_trip(): void
    {
        $newMember = User::factory()->create();

        $response = $this->actingAs($this->owner)
            ->postJson("/api/v1/trips/{$this->trip->id}/members/invite", [
                'email' => $newMember->email,
                'role' => 'member'
            ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('trip_user', [
            'trip_id' => $this->trip->id,
            'user_id' => $newMember->id,
            'status' => 'pending',
            'role' => 'member'
        ]);
    }
    public function test_member_cannot_invite_other_members(): void
    {
        $member = User::factory()->create();
        $this->trip->members()->attach($member->id, ['status' => 'accepted', 'role' => 'member']);

        $newMember = User::factory()->create();

        $response = $this->actingAs($member)
            ->postJson("/api/v1/trips/{$this->trip->id}/members/invite", [
                'email' => $newMember->email,
                'role' => 'member'
            ]);

        $response->assertStatus(403);
        $this->assertDatabaseMissing('trip_user', [
            'trip_id' => $this->trip->id,
            'user_id' => $newMember->id
        ]);
    }

    public function test_cannot_invite_nonexistent_user(): void
    {
        $response = $this->actingAs($this->owner)
            ->postJson("/api/v1/trips/{$this->trip->id}/members/invite", [
                'email' => 'nonexistent@example.com',
                'role' => 'member'
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }
    public function test_user_can_accept_trip_invitation(): void
    {
        $invitedUser = User::factory()->create();
        $this->trip->members()->attach($invitedUser->id, [
            'status' => 'pending',
            'role' => 'member'
        ]);

        $response = $this->actingAs($invitedUser)
            ->postJson("/api/v1/trips/{$this->trip->id}/accept");

        $response->assertStatus(200);
        $this->assertDatabaseHas('trip_user', [
            'trip_id' => $this->trip->id,
            'user_id' => $invitedUser->id,
            'status' => 'accepted'
        ]);
    }

    public function test_user_can_decline_trip_invitation(): void
    {
        $invitedUser = User::factory()->create();
        $this->trip->members()->attach($invitedUser->id, [
            'status' => 'pending',
            'role' => 'member'
        ]);

        $response = $this->actingAs($invitedUser)
            ->postJson("/api/v1/trips/{$this->trip->id}/decline");

        $response->assertStatus(200);
        $this->assertDatabaseHas('trip_user', [
            'trip_id' => $this->trip->id,
            'user_id' => $invitedUser->id,
            'status' => 'declined'
        ]);
    }
    public function test_owner_can_manage_members(): void
    {
        $member = User::factory()->create();
        $this->trip->members()->attach($member->id, [
            'status' => 'accepted',
            'role' => 'member'
        ]);

        $response = $this->actingAs($this->owner)
            ->deleteJson("/api/v1/trips/{$this->trip->id}/members/{$member->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('trip_user', [
            'trip_id' => $this->trip->id,
            'user_id' => $member->id
        ]);
    }

    public function test_member_cannot_manage_other_members(): void
    {
        $member1 = User::factory()->create();
        $member2 = User::factory()->create();

        $this->trip->members()->attach([
            $member1->id => ['status' => 'accepted', 'role' => 'member'],
            $member2->id => ['status' => 'accepted', 'role' => 'member']
        ]);

        $response = $this->actingAs($member1)
            ->deleteJson("/api/v1/trips/{$this->trip->id}/members/{$member2->id}");

        $response->assertStatus(403);
        $this->assertDatabaseHas('trip_user', [
            'trip_id' => $this->trip->id,
            'user_id' => $member2->id
        ]);
    }

    public function test_cannot_remove_trip_owner(): void
    {
        $member = User::factory()->create();
        $this->trip->members()->attach($member->id, [
            'status' => 'accepted',
            'role' => 'member'
        ]);

        $response = $this->actingAs($member)
            ->deleteJson("/api/v1/trips/{$this->trip->id}/members/{$this->owner->id}");

        $response->assertStatus(403);
        $this->assertDatabaseHas('trip_user', [
            'trip_id' => $this->trip->id,
            'user_id' => $this->owner->id
        ]);
    }

    public function test_editor_can_update_trip(): void
    {
        $response = $this->actingAs($this->editor)
            ->putJson("/api/v1/trips/{$this->trip->id}", [
                'name' => 'Updated Trip Name',
                'start_date' => now()->addDays(1)->format('Y-m-d'),
                'end_date' => now()->addDays(5)->format('Y-m-d'),
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('trips', [
            'id' => $this->trip->id,
            'name' => 'Updated Trip Name'
        ]);
    }

    public function test_editor_can_invite_new_members(): void
    {
        $response = $this->actingAs($this->editor)
            ->postJson("/api/v1/trips/{$this->trip->id}/members/invite", [
                'email' => $this->otherUser->email,
                'role' => 'member'
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('trip_user', [
            'trip_id' => $this->trip->id,
            'user_id' => $this->otherUser->id,
            'status' => 'pending'
        ]);
    }

    public function test_editor_can_remove_members(): void
    {
        $this->trip->members()->attach($this->otherUser->id, [
            'status' => 'accepted',
            'role' => 'member'
        ]);

        $response = $this->actingAs($this->editor)
            ->deleteJson("/api/v1/trips/{$this->trip->id}/members/{$this->otherUser->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('trip_user', [
            'trip_id' => $this->trip->id,
            'user_id' => $this->otherUser->id
        ]);
    }

    public function test_editor_cannot_remove_owner(): void
    {
        $response = $this->actingAs($this->editor)
            ->deleteJson("/api/v1/trips/{$this->trip->id}/members/{$this->owner->id}");

        $response->assertStatus(403);
        $this->assertDatabaseHas('trip_user', [
            'trip_id' => $this->trip->id,
            'user_id' => $this->owner->id
        ]);
    }

    public function test_editor_cannot_remove_other_editors(): void
    {
        $anotherEditor = User::factory()->create();
        $this->trip->members()->attach($anotherEditor->id, [
            'role' => 'editor',
            'status' => 'accepted'
        ]);

        $response = $this->actingAs($this->editor)
            ->deleteJson("/api/v1/trips/{$this->trip->id}/members/{$anotherEditor->id}");

        $response->assertStatus(403);
        $this->assertDatabaseHas('trip_user', [
            'trip_id' => $this->trip->id,
            'user_id' => $anotherEditor->id
        ]);
    }

    public function test_editor_cannot_delete_trip(): void
    {
        $response = $this->actingAs($this->editor)
            ->deleteJson("/api/v1/trips/{$this->trip->id}");

        $response->assertStatus(403);
        $this->assertDatabaseHas('trips', ['id' => $this->trip->id]);
    }
}
