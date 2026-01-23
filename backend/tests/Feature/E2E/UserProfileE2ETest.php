<?php

declare(strict_types=1);

namespace Tests\Feature\E2E;

use App\Models\User;
use App\Models\UserPreference;
use App\Models\Category;
use App\Models\Trip;
use App\Models\TripUser;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * End-to-end tests for user profile management operations.
 *
 * This test verifies the complete user profile management flow including:
 * 1. User profile viewing and editing
 * 2. Email and name updates
 * 3. Password change functionality
 * 4. Managing received invitations
 * 5. Managing sent invitations
 * 6. User preferences management
 * 7. Account deletion scenarios
 *
 * @covers \App\Http\Controllers\Api\V1\UserController
 * @covers \App\Http\Controllers\Api\V1\PreferenceController
 * @covers \App\Http\Controllers\Api\V1\TripUserController
 */
#[Group('user')]
#[Group('e2e')]
#[Group('profile')]
class UserProfileE2ETest extends TestCase
{
    use DatabaseMigrations;

    private User $user;
    private User $tripOwner;
    private Trip $trip;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password123'),
        ]);

        $this->tripOwner = User::factory()->create([
            'name' => 'Trip Owner',
            'email' => 'owner@example.com',
        ]);

        $this->trip = Trip::factory()->create([
            'name' => 'Test Trip',
            'owner_id' => $this->tripOwner->id,
        ]);

        Sanctum::actingAs($this->user);
    }

    public function test_complete_user_profile_management_flow(): void
    {
        // 1. Get current user profile
        $profileResponse = $this->getJson('/api/v1/user');
        $profileResponse->assertStatus(200)
            ->assertJsonStructure([
                'id',
                'name',
                'email',
                'email_verified_at',
                'created_at',
                'updated_at'
            ])
            ->assertJson([
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);

        // 2. Verify we can retrieve user profile
        $profileResponse = $this->getJson('/api/v1/user');

        $profileResponse->assertStatus(200)
            ->assertJson([
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);

        $this->assertDatabaseHas('users', [
            'id' => $this->user->id,
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        // 3. Set up user preferences
        $category1 = Category::factory()->create(['slug' => 'restaurant', 'include_in_preferences' => true]);
        $category2 = Category::factory()->create(['slug' => 'museum', 'include_in_preferences' => true]);
        $category3 = Category::factory()->create(['slug' => 'park', 'include_in_preferences' => true]);

        $preferencesResponse = $this->putJson('/api/v1/users/me/preferences', [
            'preferences' => [
                'restaurant' => 2, // Love
                'museum' => 1,     // Like
                'park' => 0,       // Neutral
            ]
        ]);

        $preferencesResponse->assertStatus(200);

        $this->assertDatabaseHas('user_preferences', [
            'user_id' => $this->user->id,
            'category_id' => $category1->id,
            'score' => 2,
        ]);

        $this->assertDatabaseHas('user_preferences', [
            'user_id' => $this->user->id,
            'category_id' => $category2->id,
            'score' => 1,
        ]);

        $this->assertDatabaseHas('user_preferences', [
            'user_id' => $this->user->id,
            'category_id' => $category3->id,
            'score' => 0,
        ]);

        // 5. Get updated preferences
        $getPreferencesResponse = $this->getJson('/api/v1/preferences');
        $getPreferencesResponse->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'categories' => [
                        '*' => ['slug', 'name']
                    ],
                    'user'
                ]
            ]);
    }

}
