<?php

namespace Tests\Feature\User;

use App\Models\Category;
use App\Models\Trip;
use App\Models\User;
use App\Models\UserPreference;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Test suite for User Panel functionality.
 *
 * This test verifies the core user profile and dashboard features including:
 * 1. User profile viewing and management
 * 2. Trip listing and management
 * 3. User settings and preferences
 * 4. Authentication and authorization
 *
 * @covers \App\Http\Controllers\User\UserController
 * @covers \App\Http\Controllers\Trip\TripController
 * @covers \App\Policies\UserPolicy
 */
#[Group('user')]
#[Group('profile')]
#[Group('feature')]
class UserPanelTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Set up the test environment.
     * Disables exception handling to get full error messages.
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutExceptionHandling();
    }

    /**
     * Test that an authenticated user can retrieve their own profile.
     *
     * @return void
     */
    #[Test]
    public function user_can_get_own_profile()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/user');

        $response->assertStatus(200)
            ->assertJson([
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email
            ]);
    }

    /**
     * Test that a user can view their own trips.
     *
     * @return void
     */
    #[Test]
    public function user_can_view_their_trips()
    {
        $user = User::factory()->create();
        $trip = Trip::factory()->create(['owner_id' => $user->id]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/trips');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    #[Test]
    public function user_can_view_own_trip_details()
    {
        $user = User::factory()->create();
        $trip = Trip::factory()->create(['owner_id' => $user->id]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/trips/$trip->id");

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'id' => $trip->id,
                    'name' => $trip->name,
                    'owner_id' => $user->id
                ]
            ]);
    }

    #[Test]
    public function user_cannot_view_other_users_trips()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $trip = Trip::factory()->create(['owner_id' => $user1->id]);

        $this->withoutExceptionHandling();
        try {
            $this->actingAs($user2, 'sanctum')
                ->getJson("/api/v1/trips/$trip->id");
            $this->fail('Expected AuthorizationException was not thrown');
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            $this->assertEquals('This action is unauthorized.', $e->getMessage());
            return;
        }
    }


    #[Test]
    public function user_can_update_preferences()
    {
        $category = Category::factory()->create([
            'slug' => 'test-category',
            'translations' => ['en' => 'Test Category']
        ]);

        $user = User::factory()->create();

        UserPreference::factory()->create([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'score' => 0
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->putJson('/api/v1/users/me/preferences', [
                'preferences' => [
                    'test-category' => 2
                ]
            ]);

        $response->assertStatus(200);
        $this->assertEquals(2, $user->preferences()->where('category_id', $category->id)->first()->score);
    }

    #[Test]
    public function guest_cannot_access_protected_routes()
    {
        $routes = [
            ['method' => 'getJson', 'route' => '/api/v1/user'],
            ['method' => 'getJson', 'route' => '/api/v1/trips'],
            ['method' => 'getJson', 'route' => '/api/v1/preferences'],
            ['method' => 'putJson', 'route' => '/api/v1/users/me/preferences']
        ];

        foreach ($routes as $route) {
            try {
                $this->{$route['method']}($route['route']);
                $this->fail('Expected authentication exception for ' . $route['method'] . ' ' . $route['route']);
            } catch (\Illuminate\Auth\AuthenticationException $e) {
                $this->assertEquals('Unauthenticated.', $e->getMessage());
            }
        }
    }
}
