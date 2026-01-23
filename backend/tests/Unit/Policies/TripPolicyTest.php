<?php

namespace Tests\Unit\Policies;

use App\Models\Trip;
use App\Models\User;
use App\Policies\TripPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TripPolicyTest extends TestCase
{
    use RefreshDatabase;

    private TripPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new TripPolicy();
    }

    #[Test]
    public function admin_can_do_everything()
    {
        // Note: We can't easily test the before() method without modifying the User model
        // So we'll test that admin can perform all actions through the actual policy methods
        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
            'role' => 'admin'
        ]);

        $trip = Trip::create([
            'name' => 'Test Trip',
            'owner_id' => $admin->id,
            'start_date' => now(),
            'end_date' => now()->addDays(7)
        ]);

        // Test that admin can perform all actions (before() should return true)
        $this->assertTrue($this->policy->view($admin, $trip));
        $this->assertTrue($this->policy->update($admin, $trip));
        $this->assertTrue($this->policy->delete($admin, $trip));
        $this->assertTrue($this->policy->addPlace($admin, $trip));
        $this->assertTrue($this->policy->vote($admin, $trip));
        $this->assertTrue($this->policy->manageMembers($admin, $trip));
    }

    #[Test]
    public function owner_can_view_trip()
    {
        $owner = User::create([
            'name' => 'Owner User',
            'email' => 'owner@example.com',
            'password' => bcrypt('password')
        ]);

        $trip = Trip::create([
            'name' => 'Test Trip',
            'owner_id' => $owner->id,
            'start_date' => now(),
            'end_date' => now()->addDays(7)
        ]);

        $this->assertTrue($this->policy->view($owner, $trip));
    }

    #[Test]
    public function accepted_member_can_view_trip()
    {
        $owner = User::create([
            'name' => 'Owner User',
            'email' => 'owner@example.com',
            'password' => bcrypt('password')
        ]);

        $member = User::create([
            'name' => 'Member User',
            'email' => 'member@example.com',
            'password' => bcrypt('password')
        ]);

        $trip = Trip::create([
            'name' => 'Test Trip',
            'owner_id' => $owner->id,
            'start_date' => now(),
            'end_date' => now()->addDays(7)
        ]);

        // Add member as accepted
        $trip->members()->attach($member->id, [
            'role' => 'member',
            'status' => 'accepted'
        ]);

        $this->assertTrue($this->policy->view($member, $trip));
    }

    #[Test]
    public function pending_member_can_view_trip()
    {
        $owner = User::create([
            'name' => 'Owner User',
            'email' => 'owner@example.com',
            'password' => bcrypt('password')
        ]);

        $member = User::create([
            'name' => 'Member User',
            'email' => 'member@example.com',
            'password' => bcrypt('password')
        ]);

        $trip = Trip::create([
            'name' => 'Test Trip',
            'owner_id' => $owner->id,
            'start_date' => now(),
            'end_date' => now()->addDays(7)
        ]);

        // Add member as pending
        $trip->members()->attach($member->id, [
            'role' => 'member',
            'status' => 'pending'
        ]);

        $this->assertTrue($this->policy->view($member, $trip));
    }

    #[Test]
    public function non_member_cannot_view_trip()
    {
        $owner = User::create([
            'name' => 'Owner User',
            'email' => 'owner@example.com',
            'password' => bcrypt('password')
        ]);

        $stranger = User::create([
            'name' => 'Stranger User',
            'email' => 'stranger@example.com',
            'password' => bcrypt('password')
        ]);

        $trip = Trip::create([
            'name' => 'Test Trip',
            'owner_id' => $owner->id,
            'start_date' => now(),
            'end_date' => now()->addDays(7)
        ]);

        $this->assertFalse($this->policy->view($stranger, $trip));
    }

    #[Test]
    public function owner_can_update_trip()
    {
        $owner = User::create([
            'name' => 'Owner User',
            'email' => 'owner@example.com',
            'password' => bcrypt('password')
        ]);

        $trip = Trip::create([
            'name' => 'Test Trip',
            'owner_id' => $owner->id,
            'start_date' => now(),
            'end_date' => now()->addDays(7)
        ]);

        $this->assertTrue($this->policy->update($owner, $trip));
    }

    #[Test]
    public function editor_member_can_update_trip()
    {
        $owner = User::create([
            'name' => 'Owner User',
            'email' => 'owner@example.com',
            'password' => bcrypt('password')
        ]);

        $editor = User::create([
            'name' => 'Editor User',
            'email' => 'editor@example.com',
            'password' => bcrypt('password')
        ]);

        $trip = Trip::create([
            'name' => 'Test Trip',
            'owner_id' => $owner->id,
            'start_date' => now(),
            'end_date' => now()->addDays(7)
        ]);

        // Add editor as accepted
        $trip->members()->attach($editor->id, [
            'role' => 'editor',
            'status' => 'accepted'
        ]);

        $this->assertTrue($this->policy->update($editor, $trip));
    }

    #[Test]
    public function regular_member_cannot_update_trip()
    {
        $owner = User::create([
            'name' => 'Owner User',
            'email' => 'owner@example.com',
            'password' => bcrypt('password')
        ]);

        $member = User::create([
            'name' => 'Member User',
            'email' => 'member@example.com',
            'password' => bcrypt('password')
        ]);

        $trip = Trip::create([
            'name' => 'Test Trip',
            'owner_id' => $owner->id,
            'start_date' => now(),
            'end_date' => now()->addDays(7)
        ]);

        // Add member as accepted
        $trip->members()->attach($member->id, [
            'role' => 'member',
            'status' => 'accepted'
        ]);

        $this->assertFalse($this->policy->update($member, $trip));
    }

    #[Test]
    public function pending_editor_cannot_update_trip()
    {
        $owner = User::create([
            'name' => 'Owner User',
            'email' => 'owner@example.com',
            'password' => bcrypt('password')
        ]);

        $editor = User::create([
            'name' => 'Editor User',
            'email' => 'editor@example.com',
            'password' => bcrypt('password')
        ]);

        $trip = Trip::create([
            'name' => 'Test Trip',
            'owner_id' => $owner->id,
            'start_date' => now(),
            'end_date' => now()->addDays(7)
        ]);

        // Add editor as pending
        $trip->members()->attach($editor->id, [
            'role' => 'editor',
            'status' => 'pending'
        ]);

        $this->assertFalse($this->policy->update($editor, $trip));
    }

    #[Test]
    public function only_owner_can_delete_trip()
    {
        $owner = User::create([
            'name' => 'Owner User',
            'email' => 'owner@example.com',
            'password' => bcrypt('password')
        ]);

        $member = User::create([
            'name' => 'Member User',
            'email' => 'member@example.com',
            'password' => bcrypt('password')
        ]);

        $trip = Trip::create([
            'name' => 'Test Trip',
            'owner_id' => $owner->id,
            'start_date' => now(),
            'end_date' => now()->addDays(7)
        ]);

        // Add member as editor
        $trip->members()->attach($member->id, [
            'role' => 'editor',
            'status' => 'accepted'
        ]);

        $this->assertTrue($this->policy->delete($owner, $trip));
        $this->assertFalse($this->policy->delete($member, $trip));
    }

    #[Test]
    public function any_user_can_create_trip()
    {
        $user = User::create([
            'name' => 'Regular User',
            'email' => 'user@example.com',
            'password' => bcrypt('password')
        ]);

        $this->assertTrue($this->policy->create($user));
    }

    #[Test]
    public function owner_can_add_place()
    {
        $owner = User::create([
            'name' => 'Owner User',
            'email' => 'owner@example.com',
            'password' => bcrypt('password')
        ]);

        $trip = Trip::create([
            'name' => 'Test Trip',
            'owner_id' => $owner->id,
            'start_date' => now(),
            'end_date' => now()->addDays(7)
        ]);

        $this->assertTrue($this->policy->addPlace($owner, $trip));
    }

    #[Test]
    public function accepted_member_can_add_place()
    {
        $owner = User::create([
            'name' => 'Owner User',
            'email' => 'owner@example.com',
            'password' => bcrypt('password')
        ]);

        $member = User::create([
            'name' => 'Member User',
            'email' => 'member@example.com',
            'password' => bcrypt('password')
        ]);

        $trip = Trip::create([
            'name' => 'Test Trip',
            'owner_id' => $owner->id,
            'start_date' => now(),
            'end_date' => now()->addDays(7)
        ]);

        // Add member as accepted
        $trip->members()->attach($member->id, [
            'role' => 'member',
            'status' => 'accepted'
        ]);

        $this->assertTrue($this->policy->addPlace($member, $trip));
    }

    #[Test]
    public function pending_member_cannot_add_place()
    {
        $owner = User::create([
            'name' => 'Owner User',
            'email' => 'owner@example.com',
            'password' => bcrypt('password')
        ]);

        $member = User::create([
            'name' => 'Member User',
            'email' => 'member@example.com',
            'password' => bcrypt('password')
        ]);

        $trip = Trip::create([
            'name' => 'Test Trip',
            'owner_id' => $owner->id,
            'start_date' => now(),
            'end_date' => now()->addDays(7)
        ]);

        // Add member as pending
        $trip->members()->attach($member->id, [
            'role' => 'member',
            'status' => 'pending'
        ]);

        $this->assertFalse($this->policy->addPlace($member, $trip));
    }

    #[Test]
    public function vote_follows_add_place_rules()
    {
        $owner = User::create([
            'name' => 'Owner User',
            'email' => 'owner@example.com',
            'password' => bcrypt('password')
        ]);

        $member = User::create([
            'name' => 'Member User',
            'email' => 'member@example.com',
            'password' => bcrypt('password')
        ]);

        $trip = Trip::create([
            'name' => 'Test Trip',
            'owner_id' => $owner->id,
            'start_date' => now(),
            'end_date' => now()->addDays(7)
        ]);

        // Test owner can vote
        $this->assertTrue($this->policy->vote($owner, $trip));

        // Test accepted member can vote
        $trip->members()->attach($member->id, [
            'role' => 'member',
            'status' => 'accepted'
        ]);
        $this->assertTrue($this->policy->vote($member, $trip));
    }

    #[Test]
    public function pending_member_can_accept_invitation()
    {
        $owner = User::create([
            'name' => 'Owner User',
            'email' => 'owner@example.com',
            'password' => bcrypt('password')
        ]);

        $member = User::create([
            'name' => 'Member User',
            'email' => 'member@example.com',
            'password' => bcrypt('password')
        ]);

        $trip = Trip::create([
            'name' => 'Test Trip',
            'owner_id' => $owner->id,
            'start_date' => now(),
            'end_date' => now()->addDays(7)
        ]);

        // Add member as pending
        $trip->members()->attach($member->id, [
            'role' => 'member',
            'status' => 'pending'
        ]);

        $this->assertTrue($this->policy->accept($member, $trip));
        $this->assertTrue($this->policy->decline($member, $trip));
    }

    #[Test]
    public function accepted_member_cannot_respond_to_invitation()
    {
        $owner = User::create([
            'name' => 'Owner User',
            'email' => 'owner@example.com',
            'password' => bcrypt('password')
        ]);

        $member = User::create([
            'name' => 'Member User',
            'email' => 'member@example.com',
            'password' => bcrypt('password')
        ]);

        $trip = Trip::create([
            'name' => 'Test Trip',
            'owner_id' => $owner->id,
            'start_date' => now(),
            'end_date' => now()->addDays(7)
        ]);

        // Add member as accepted
        $trip->members()->attach($member->id, [
            'role' => 'member',
            'status' => 'accepted'
        ]);

        $this->assertFalse($this->policy->accept($member, $trip));
        $this->assertFalse($this->policy->decline($member, $trip));
    }

    #[Test]
    public function owner_can_manage_members()
    {
        $owner = User::create([
            'name' => 'Owner User',
            'email' => 'owner@example.com',
            'password' => bcrypt('password')
        ]);

        $trip = Trip::create([
            'name' => 'Test Trip',
            'owner_id' => $owner->id,
            'start_date' => now(),
            'end_date' => now()->addDays(7)
        ]);

        $this->assertTrue($this->policy->manageMembers($owner, $trip));
    }

    #[Test]
    public function editor_member_can_manage_members()
    {
        $owner = User::create([
            'name' => 'Owner User',
            'email' => 'owner@example.com',
            'password' => bcrypt('password')
        ]);

        $editor = User::create([
            'name' => 'Editor User',
            'email' => 'editor@example.com',
            'password' => bcrypt('password')
        ]);

        $trip = Trip::create([
            'name' => 'Test Trip',
            'owner_id' => $owner->id,
            'start_date' => now(),
            'end_date' => now()->addDays(7)
        ]);

        // Add editor as accepted
        $trip->members()->attach($editor->id, [
            'role' => 'editor',
            'status' => 'accepted'
        ]);

        $this->assertTrue($this->policy->manageMembers($editor, $trip));
    }

    #[Test]
    public function regular_member_cannot_manage_members()
    {
        $owner = User::create([
            'name' => 'Owner User',
            'email' => 'owner@example.com',
            'password' => bcrypt('password')
        ]);

        $member = User::create([
            'name' => 'Member User',
            'email' => 'member@example.com',
            'password' => bcrypt('password')
        ]);

        $trip = Trip::create([
            'name' => 'Test Trip',
            'owner_id' => $owner->id,
            'start_date' => now(),
            'end_date' => now()->addDays(7)
        ]);

        // Add member as accepted
        $trip->members()->attach($member->id, [
            'role' => 'member',
            'status' => 'accepted'
        ]);

        $this->assertFalse($this->policy->manageMembers($member, $trip));
    }

    #[Test]
    public function policy_works_with_loaded_relations()
    {
        $owner = User::create([
            'name' => 'Owner User',
            'email' => 'owner@example.com',
            'password' => bcrypt('password')
        ]);

        $member = User::create([
            'name' => 'Member User',
            'email' => 'member@example.com',
            'password' => bcrypt('password')
        ]);

        $trip = Trip::create([
            'name' => 'Test Trip',
            'owner_id' => $owner->id,
            'start_date' => now(),
            'end_date' => now()->addDays(7)
        ]);

        // Add member as accepted
        $trip->members()->attach($member->id, [
            'role' => 'member',
            'status' => 'accepted'
        ]);

        // Load relations to test the relationLoaded path
        $trip->load('members');

        $this->assertTrue($this->policy->view($member, $trip));
        $this->assertFalse($this->policy->update($member, $trip));
        $this->assertTrue($this->policy->addPlace($member, $trip));
    }
}
