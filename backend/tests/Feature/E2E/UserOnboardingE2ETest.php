<?php

declare(strict_types=1);

namespace Tests\Feature\E2E;

use App\Models\Category;
use App\Models\User;
use Illuminate\Cache\RateLimiter;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase\ApiTestCase;
use Illuminate\Foundation\Testing\DatabaseMigrations;

/**
 * End-to-end test for complete user registration and onboarding flow.
 *
 * This test verifies the complete user journey from registration through onboarding:
 * 1. User registration with email verification
 * 2. Email verification process
 * 3. Profile completion
 * 4. Initial preference selection
 * 5. First trip creation tutorial
 *
 * @covers \App\Http\Controllers\Auth\Auth\RegisteredUserController
 * @covers \App\Http\Controllers\User\ProfileController
 * @covers \App\Http\Controllers\User\PreferenceController
 * @covers \App\Http\Controllers\Trip\TripController
 */
#[Group('auth')]
#[Group('e2e')]
class UserOnboardingE2ETest extends ApiTestCase
{
    use DatabaseMigrations;

    private const TEST_EMAIL = 'newuser@example.com';
    private const TEST_PASSWORD = 'SecurePass123!';
    private const TEST_NAME = 'New Test User';

    /**
     * Create a test category.
     *
     * @param array $attributes
     * @return Category
     */
    private function createCategory(array $attributes = []): Category
    {
        $defaults = [
            'slug' => 'test-category-' . uniqid(),
            'include_in_preferences' => true,
            'translations' => ['en' => 'Test Category', 'pl' => 'Kategoria testowa']
        ];

        return Category::factory()->create(array_merge($defaults, $attributes));
    }

    /**
     * Disable rate limiting for tests
     */
    protected bool $enableRateLimiting = false;

    /**
     * Set up the test environment.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(VerifyCsrfToken::class);
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ValidateSignature::class);

        Notification::fake();
        config(['session.driver' => 'array']);

        // Completely disable rate limiting for all tests in this class
        $this->app->bind(RateLimiter::class, function () {
            $mock = $this->createMock(RateLimiter::class);
            $mock->method('tooManyAttempts')->willReturn(false);
            $mock->method('hit')->willReturn(1);
            $mock->method('availableIn')->willReturn(0);
            return $mock;
        });

        // Clear any existing rate limiting state
        $this->clearRateLimits();
    }

    public function test_complete_onboarding_flow(): void
    {
        $registerResponse = $this->postJson('/api/v1/register', [
            'name' => self::TEST_NAME,
            'email' => self::TEST_EMAIL,
            'password' => self::TEST_PASSWORD,
            'password_confirmation' => self::TEST_PASSWORD,
        ]);

        $registerResponse->assertStatus(201);
        $registerResponse->assertJsonStructure([
            'user' => ['id', 'name', 'email'],
            'token'
        ]);

        $userId = $registerResponse->json('user.id');
        $token = $registerResponse->json('token');

        $this->assertDatabaseHas('users', [
            'id' => $userId,
            'email' => self::TEST_EMAIL,
            'email_verified_at' => null,
        ]);

        $user = User::find($userId);

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            [
                'id' => $user->getAttribute('id'),
                'hash' => sha1($user->getAttribute('email'))
            ]
        );

        $verificationResponse = $this->actingAs($user, 'web')
            ->get($verificationUrl);

        $user->refresh();
        $this->assertTrue($user->hasVerifiedEmail());

        $sightseeing = $this->createCategory(['slug' => 'sightseeing', 'include_in_preferences' => true]);
        $food = $this->createCategory(['slug' => 'food', 'include_in_preferences' => true]);
        $adventure = $this->createCategory(['slug' => 'adventure', 'include_in_preferences' => true]);

        $preferences = [
            'preferences' => [
                'sightseeing' => 2,
                'food' => 2,
                'adventure' => 1,
            ]
        ];

        $preferencesResponse = $this->withToken($token)
            ->putJson('/api/v1/users/me/preferences', $preferences);

        $preferencesResponse->assertStatus(200);

        $this->assertDatabaseHas('user_preferences', [
            'user_id' => $userId,
            'score' => 2,
            'category_id' => $sightseeing->id,
        ]);

        $this->assertDatabaseHas('user_preferences', [
            'user_id' => $userId,
            'score' => 2,
            'category_id' => $food->id,
        ]);

        $this->assertDatabaseHas('user_preferences', [
            'user_id' => $userId,
            'score' => 1,
            'category_id' => $adventure->id,
        ]);

        $tripData = [
            'name' => 'My First Adventure',
            'description' => 'Exploring new places',
            'start_date' => now()->addWeek()->format('Y-m-d'),
            'end_date' => now()->addWeeks(2)->format('Y-m-d'),
            'start_latitude' => 52.2297,
            'start_longitude' => 21.0122,
        ];

        $tripResponse = $this->withToken($token)
            ->postJson('/api/v1/trips', $tripData);

        $tripResponse->assertStatus(201);
        $tripResponse->assertJson([
            'message' => 'Trip created successfully',
            'data' => [
                'name' => $tripData['name'],
                'description' => $tripData['description']
            ]
        ]);

        $this->assertDatabaseHas('trips', [
            'owner_id' => $userId,
            'name' => $tripData['name']
        ]);
    }
}
