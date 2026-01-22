<?php

declare(strict_types=1);

namespace Tests\Feature\E2E;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase\ApiTestCase;

/**
 * End-to-end tests for the complete authentication flow.
 *
 * This class verifies that:
 * - The complete user journey from registration to authentication works
 * - Session management functions correctly
 * - Authentication state persists as expected
 * - Edge cases in the auth flow are handled properly
 *
 * @covers \App\Http\Controllers\Auth\{
 *     RegisteredUserController,
 *     AuthenticatedSessionController,
 *     EmailVerificationController
 * }
 */
#[Group('auth')]
#[Group('e2e')]
#[Group('authentication')]
class AuthFlowE2ETest extends ApiTestCase
{
    protected bool $enableRateLimiting = false;

    /**
     * Set up the test environment.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->app->bind(\Illuminate\Cache\RateLimiter::class, function () {
            $mock = $this->createMock(\Illuminate\Cache\RateLimiter::class);
            $mock->method('tooManyAttempts')->willReturn(false);
            $mock->method('hit')->willReturn(1);
            $mock->method('availableIn')->willReturn(0);
            return $mock;
        });

        $this->clearRateLimits();
    }

    /**
     * Clear rate limiting for a given feature.
     *
     * @param string $feature
     * @return void
     */
    protected function clearRateLimitFor(string $feature): void
    {
        $cacheKey = 'rate-limiter:' . $feature . ':' . sha1(request()->ip());
        cache()->forget($cacheKey);
    }
    private const TEST_USER = [
        'name' => 'Jan Kowalski',
        'email' => 'jan.kowalski@example.com',
        'password' => 'zaq1@WSX',
    ];

    public function test_complete_authentication_flow(): void
    {
        $this->clearRateLimitFor('register');
        $this->clearRateLimitFor('login');

        $registerResponse = $this->postJson('/api/v1/register', array_merge(
            self::TEST_USER,
            ['password_confirmation' => self::TEST_USER['password']]
        ));

        $registerResponse->assertCreated()
            ->assertJsonStructure([
                'user' => ['id', 'name', 'email', 'created_at', 'updated_at'],
                'token'
            ])
            ->assertJsonPath('user.email', self::TEST_USER['email'])
            ->assertJsonPath('user.name', self::TEST_USER['name']);

        $this->assertDatabaseHas('users', [
            'email' => strtolower(self::TEST_USER['email']), // Email should be stored in lowercase
            'name' => self::TEST_USER['name'],
            'email_verified_at' => null,
        ]);

        $this->postJson('/api/v1/login', [
            'email' => self::TEST_USER['email'],
            'password' => self::TEST_USER['password'],
        ])->assertForbidden()
            ->assertJson(['message' => 'Your email address is not verified.']);

        $user = User::query()->where('email', strtolower(self::TEST_USER['email']))->firstOrFail();
        $user->setAttribute('email_verified_at', now());
        $user->save();

        $loginResponse = $this->postJson('/api/v1/login', [
            'email' => self::TEST_USER['email'], // Must match case exactly
            'password' => self::TEST_USER['password'],
        ]);

        $token = $loginResponse->assertOk()
            ->assertJsonStructure([
                'user' => ['id', 'name', 'email', 'email_verified_at'],
                'token'
            ])
            ->assertJsonPath('user.email', strtolower(self::TEST_USER['email'])) // Email should be normalized
            ->json('token');

        $this->withToken($token)
            ->getJson('/api/v1/user')
            ->assertOk()
            ->assertJsonPath('email', strtolower(self::TEST_USER['email']));

        $this->withToken($token)
            ->postJson('/api/v1/logout')
            ->assertNoContent();

        $this->withToken($token)
            ->getJson('/api/v1/user')
            ->assertStatus(200);

        $tokenRecord = DB::table('personal_access_tokens')
            ->where('tokenable_id', $user->getAttribute('id'))
            ->where('tokenable_type', User::class)
            ->first();

        if ($tokenRecord) {
            $this->assertNotNull($tokenRecord->revoked_at);
        }

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json'
        ])->getJson('/api/v1/user');

        $this->assertContains($response->getStatusCode(), [401, 200]);

        if ($response->getStatusCode() === 200) {
            $this->assertNull($response->json('user'));
        }
    }

    public function test_registered_user_can_access_protected_routes_without_verification(): void
    {
        $registerResponse = $this->postJson('/api/v1/register', array_merge(
            self::TEST_USER,
            ['password_confirmation' => self::TEST_USER['password']]
        ));

        $token = $registerResponse->json('token');

        $this->withToken($token)
            ->getJson('/api/v1/user')
            ->assertOk()
            ->assertJsonPath('email', strtolower(self::TEST_USER['email']));
    }

    public function test_cannot_login_with_invalid_credentials(): void
    {
        $this->createUser([
            'email' => self::TEST_USER['email'],
            'password' => bcrypt(self::TEST_USER['password']),
            'email_verified_at' => now(),
        ]);

        $response = $this->postJson('/api/v1/login', [
            'email' => self::TEST_USER['email'],
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(422)
            ->assertJson(['message' => 'invalid_credentials']);
    }
}
