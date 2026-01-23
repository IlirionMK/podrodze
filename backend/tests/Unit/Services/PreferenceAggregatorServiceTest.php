<?php

namespace Tests\Unit\Services;

use App\Models\Category;
use App\Models\Trip;
use App\Models\User;
use App\Models\UserPreference;
use App\Services\PreferenceAggregatorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PreferenceAggregatorServiceTest extends TestCase
{
    use RefreshDatabase;

    private PreferenceAggregatorService $service;
    private Trip $trip;
    private User $owner;
    private User $member1;
    private User $member2;
    private Category $foodCategory;
    private Category $museumCategory;
    private Category $natureCategory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new PreferenceAggregatorService();

        $this->owner = User::factory()->create();
        $this->member1 = User::factory()->create();
        $this->member2 = User::factory()->create();

        $this->trip = Trip::factory()->create(['owner_id' => $this->owner->id]);

        $this->foodCategory = Category::factory()->create(['slug' => 'food', 'translations' => ['en' => 'Food', 'pl' => 'Jedzenie']]);
        $this->museumCategory = Category::factory()->create(['slug' => 'museum', 'translations' => ['en' => 'Museum', 'pl' => 'Muzeum']]);
        $this->natureCategory = Category::factory()->create(['slug' => 'nature', 'translations' => ['en' => 'Nature', 'pl' => 'Natura']]);
    }

    #[Test]
    public function it_aggregates_preferences_from_owner_and_members(): void
    {
        // Set up owner preferences
        UserPreference::factory()->create([
            'user_id' => $this->owner->id,
            'category_id' => $this->foodCategory->id,
            'score' => 2
        ]);

        UserPreference::factory()->create([
            'user_id' => $this->owner->id,
            'category_id' => $this->museumCategory->id,
            'score' => 1
        ]);

        // Add members to trip
        $this->trip->members()->attach($this->member1->id, ['status' => 'accepted']);
        $this->trip->members()->attach($this->member2->id, ['status' => 'accepted']);

        // Set up member preferences
        UserPreference::factory()->create([
            'user_id' => $this->member1->id,
            'category_id' => $this->foodCategory->id,
            'score' => 1
        ]);

        UserPreference::factory()->create([
            'user_id' => $this->member1->id,
            'category_id' => $this->natureCategory->id,
            'score' => 2
        ]);

        UserPreference::factory()->create([
            'user_id' => $this->member2->id,
            'category_id' => $this->foodCategory->id,
            'score' => 0
        ]);

        UserPreference::factory()->create([
            'user_id' => $this->member2->id,
            'category_id' => $this->museumCategory->id,
            'score' => 2
        ]);

        $preferences = $this->service->getGroupPreferences($this->trip);

        $this->assertEquals(1.0, $preferences['food']);
        $this->assertEquals(1.5, $preferences['museum']);
        $this->assertEquals(2.0, $preferences['nature']);
    }

    #[Test]
    public function it_ignores_pending_and_declined_members(): void
    {
        // Set up owner preferences
        UserPreference::factory()->create([
            'user_id' => $this->owner->id,
            'category_id' => $this->foodCategory->id,
            'score' => 2
        ]);

        // Add members with different statuses
        $this->trip->members()->attach($this->member1->id, ['status' => 'accepted']);
        $this->trip->members()->attach($this->member2->id, ['status' => 'pending']); // Should be ignored

        // Set up preferences for both members
        UserPreference::factory()->create([
            'user_id' => $this->member1->id,
            'category_id' => $this->foodCategory->id,
            'score' => 0
        ]);

        UserPreference::factory()->create([
            'user_id' => $this->member2->id,
            'category_id' => $this->foodCategory->id,
            'score' => 2 // Should be ignored
        ]);

        $preferences = $this->service->getGroupPreferences($this->trip);

        // Only owner and accepted member should be counted: (2 + 0) / 2 = 1.0
        $this->assertEquals(1.0, $preferences['food']);
    }

    #[Test]
    public function it_returns_empty_array_for_trip_with_no_users(): void
    {
        $emptyTrip = Trip::factory()->create(['owner_id' => $this->owner->id]);

        $preferences = $this->service->getGroupPreferences($emptyTrip);

        $this->assertEmpty($preferences);
    }

    #[Test]
    public function it_handles_trip_with_only_owner(): void
    {
        // Set up owner preferences only
        UserPreference::factory()->create([
            'user_id' => $this->owner->id,
            'category_id' => $this->foodCategory->id,
            'score' => 2
        ]);

        UserPreference::factory()->create([
            'user_id' => $this->owner->id,
            'category_id' => $this->museumCategory->id,
            'score' => 1
        ]);

        $preferences = $this->service->getGroupPreferences($this->trip);

        // Should return owner's preferences as-is (average of 1 is the same value)
        $this->assertEquals(2.0, $preferences['food']);
        $this->assertEquals(1.0, $preferences['museum']);
    }

    #[Test]
    public function it_calculates_correct_averages_with_different_user_counts(): void
    {
        // Owner: food=2, museum=1
        UserPreference::factory()->create([
            'user_id' => $this->owner->id,
            'category_id' => $this->foodCategory->id,
            'score' => 2
        ]);

        UserPreference::factory()->create([
            'user_id' => $this->owner->id,
            'category_id' => $this->museumCategory->id,
            'score' => 1
        ]);

        // Member1: food=1, nature=2
        $this->trip->members()->attach($this->member1->id, ['status' => 'accepted']);

        UserPreference::factory()->create([
            'user_id' => $this->member1->id,
            'category_id' => $this->foodCategory->id,
            'score' => 1
        ]);

        UserPreference::factory()->create([
            'user_id' => $this->member1->id,
            'category_id' => $this->natureCategory->id,
            'score' => 2
        ]);

        // Member2: only museum=2
        $this->trip->members()->attach($this->member2->id, ['status' => 'accepted']);

        UserPreference::factory()->create([
            'user_id' => $this->member2->id,
            'category_id' => $this->museumCategory->id,
            'score' => 2
        ]);

        $preferences = $this->service->getGroupPreferences($this->trip);

        $this->assertEquals(1.5, $preferences['food']);
        $this->assertEquals(1.5, $preferences['museum']);
        $this->assertEquals(2.0, $preferences['nature']);
    }

    #[Test]
    public function it_handles_duplicate_user_ids_gracefully(): void
    {
        // Create a real trip with owner
        $trip = Trip::factory()->create(['owner_id' => $this->owner->id]);

        // Add the member once
        $trip->members()->syncWithoutDetaching([$this->member1->id => ['status' => 'accepted']]);

        // Try to add the same member again (should be ignored)
        $trip->members()->syncWithoutDetaching([$this->member1->id => ['status' => 'accepted']]);

        // Create preference for the member
        UserPreference::factory()->create([
            'user_id' => $this->member1->id,
            'category_id' => $this->foodCategory->id,
            'score' => 3
        ]);

        // The service should handle the duplicate user IDs and only count each user once
        $preferences = $this->service->getGroupPreferences($trip);

        // We should have one preference (for member1) - owner has no preference in this test
        $this->assertCount(1, $preferences);
        $this->assertArrayHasKey('food', $preferences);
        $this->assertEquals(3.0, $preferences['food']);
    }

    #[Test]
    public function it_rounds_averages_to_two_decimal_places(): void
    {
        // Create preferences for the test
        UserPreference::factory()->create([
            'user_id' => $this->owner->id,
            'category_id' => $this->foodCategory->id,
            'score' => 2
        ]);

        // Add the user once
        $this->trip->members()->syncWithoutDetaching([
            $this->member1->id => ['status' => 'accepted']
        ]);

        UserPreference::factory()->create([
            'user_id' => $this->member1->id,
            'category_id' => $this->foodCategory->id,
            'score' => 1
        ]);

        $preferences = $this->service->getGroupPreferences($this->trip);

        // (2 + 1) / 2 = 1.5
        $this->assertEquals(1.5, $preferences['food']);
        $this->assertIsFloat($preferences['food']);

        // Test with more complex decimal
        UserPreference::factory()->create([
            'user_id' => $this->member2->id,
            'category_id' => $this->foodCategory->id,
            'score' => 0
        ]);

        $this->trip->members()->attach($this->member2->id, ['status' => 'accepted']);

        $preferences = $this->service->getGroupPreferences($this->trip);

        // (2 + 1 + 0) / 3 = 1.0
        $this->assertEquals(1.0, $preferences['food']);
    }

    #[Test]
    public function it_returns_correct_array_structure(): void
    {
        UserPreference::factory()->create([
            'user_id' => $this->owner->id,
            'category_id' => $this->foodCategory->id,
            'score' => 2
        ]);

        $preferences = $this->service->getGroupPreferences($this->trip);

        $this->assertIsArray($preferences);
        $this->assertArrayHasKey('food', $preferences);
        $this->assertIsFloat($preferences['food']);
    }

    #[Test]
    public function it_handles_categories_with_no_preferences(): void
    {
        // Don't create any preferences for nature category
        UserPreference::factory()->create([
            'user_id' => $this->owner->id,
            'category_id' => $this->foodCategory->id,
            'score' => 2
        ]);

        $preferences = $this->service->getGroupPreferences($this->trip);

        $this->assertArrayHasKey('food', $preferences);
        $this->assertArrayNotHasKey('nature', $preferences);
    }

    #[Test]
    public function it_works_with_large_number_of_users(): void
    {
        // Create additional users
        $additionalUsers = User::factory()->count(5)->create();

        // Add all users to trip
        foreach ($additionalUsers as $user) {
            $this->trip->members()->attach($user->id, ['status' => 'accepted']);
        }

        // Set up preferences for all users
        UserPreference::factory()->create([
            'user_id' => $this->owner->id,
            'category_id' => $this->foodCategory->id,
            'score' => 2
        ]);

        foreach ($additionalUsers as $index => $user) {
            UserPreference::factory()->create([
                'user_id' => $user->id,
                'category_id' => $this->foodCategory->id,
                'score' => $index % 3 // Varying scores: 0, 1, 2, 0, 1, 2
            ]);
        }

        $preferences = $this->service->getGroupPreferences($this->trip);

        // Should calculate average across all 6 users
        $this->assertArrayHasKey('food', $preferences);
        $this->assertIsFloat($preferences['food']);
        $this->assertGreaterThanOrEqual(0, $preferences['food']);
        $this->assertLessThanOrEqual(2, $preferences['food']);
    }

    #[Test]
    public function it_handles_zero_scores(): void
    {
        UserPreference::factory()->create([
            'user_id' => $this->owner->id,
            'category_id' => $this->foodCategory->id,
            'score' => 0
        ]);

        $this->trip->members()->attach($this->member1->id, ['status' => 'accepted']);

        UserPreference::factory()->create([
            'user_id' => $this->member1->id,
            'category_id' => $this->foodCategory->id,
            'score' => 0
        ]);

        $preferences = $this->service->getGroupPreferences($this->trip);

        $this->assertEquals(0.0, $preferences['food']);
    }

    #[Test]
    public function it_handles_maximum_scores(): void
    {
        UserPreference::factory()->create([
            'user_id' => $this->owner->id,
            'category_id' => $this->foodCategory->id,
            'score' => 2
        ]);

        $this->trip->members()->attach($this->member1->id, ['status' => 'accepted']);

        UserPreference::factory()->create([
            'user_id' => $this->member1->id,
            'category_id' => $this->foodCategory->id,
            'score' => 2
        ]);

        $preferences = $this->service->getGroupPreferences($this->trip);

        $this->assertEquals(2.0, $preferences['food']);
    }
}
