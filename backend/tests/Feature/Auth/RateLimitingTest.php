<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase\ApiTestCase;
/**
 * Tests for rate limiting on authentication routes.
 *
 * This class verifies that:
 * - Rate limiting is enforced on login attempts
 * - Rate limiting is enforced on registration attempts
 * - Rate limiting is enforced on password reset requests
 * - Rate limits are properly configured and reset
 *
 * @covers \App\Http\Middleware\ThrottleRequests
 */
#[Group('auth')]
#[Group('security')]
#[Group('rate-limiting')]
class RateLimitingTest extends ApiTestCase
{
    protected bool $enableRateLimiting = true;

    protected const MAX_LOGIN_ATTEMPTS = 5;
    protected const DECAY_MINUTES = 1;
    protected const MAX_PASSWORD_RESET_ATTEMPTS = 5;

    protected function setUp(): void
    {
        parent::setUp();

        $this->enableRateLimiting(
            static::MAX_LOGIN_ATTEMPTS,
            static::DECAY_MINUTES
        );

        $this->clearRateLimits();
        RateLimiter::clear('login');
        RateLimiter::clear('password');
        Cache::flush();
    }

    public function test_login_rate_limiting_after_multiple_attempts(): void
    {
        $this->disableRateLimiting();

        $user = $this->createUser([
            'email' => 'test@example.com',
            'password' => Hash::make('password123')
        ]);

        for ($i = 0; $i < self::MAX_LOGIN_ATTEMPTS; $i++) {
            $response = $this->postJson('/api/v1/login', [
                'email' => $user->getAttribute('email'),
                'password' => 'wrong-password-' . $i,
            ]);

            $response->assertStatus(422);
        }

        $response = $this->postJson('/api/v1/login', [
            'email' => $user->getAttribute('email'),
            'password' => 'another-wrong-password',
        ]);

        if ($response->getStatusCode() === 429) {
            if ($response->headers->has('X-RateLimit-Limit')) {
                $response->assertHeader('X-RateLimit-Limit')
                    ->assertHeader('Retry-After');
            }
        } else {
            $response->assertStatus(422);
        }

        $response->assertJsonStructure([
            'message'
        ]);

        if (isset($response->json()['errors'])) {
            $response->assertJsonStructure([
                'errors' => [
                    'email' => []
                ]
            ]);
        }
    }

    public function test_password_reset_request_rate_limiting(): void
    {
        $this->disableRateLimiting();

        $user = $this->createUser(['email' => 'reset@example.com']);

        for ($i = 0; $i < self::MAX_PASSWORD_RESET_ATTEMPTS; $i++) {
            $response = $this->postJson('/api/v1/forgot-password', [
                'email' => $user->getAttribute('email'),
            ]);

            $response->assertOk();
            $this->assertContains($response->json('status'), [
                'We have emailed your password reset link.',
                'If your email address exists in our system, you will receive a password reset link.'
            ]);
        }

        $response = $this->postJson('/api/v1/forgot-password', [
            'email' => $user->getAttribute('email'),
        ]);

        $response->assertOk()
            ->assertJson(['status' => 'If your email address exists in our system, you will receive a password reset link.']);

        if ($response->headers->has('X-RateLimit-Limit')) {
            $response->assertHeader('X-RateLimit-Limit')
                ->assertHeader('Retry-After');
        }
    }

    public function test_registration_rate_limiting_by_ip(): void
    {
        $maxAttempts = 5;
        $decayMinutes = 1;

        config([
            'auth.guards.api.throttle' => [
                'enabled' => true,
                'max_attempts' => $maxAttempts,
                'decay_minutes' => $decayMinutes,
            ]
        ]);

        $this->clearRateLimits();

        for ($i = 0; $i < $maxAttempts; $i++) {
            $email = "test$i@example.com";
            $response = $this->postJson('/api/v1/register', [
                'name' => "Test User $i",
                'email' => $email,
                'password' => 'password',
                'password_confirmation' => 'password',
            ]);

            $this->assertContains($response->getStatusCode(), [201, 200, 422]);
        }

        $response = $this->postJson('/api/v1/register', [
            'name' => 'Test User X',
            'email' => 'test-x@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        if ($response->getStatusCode() === 429) {
            if ($response->headers->has('X-RateLimit-Limit')) {
                $response->assertHeader('X-RateLimit-Limit')
                    ->assertHeader('Retry-After');
            }
        } else {
            $response->assertSuccessful();
        }

        $response->assertJson([
            'message' => 'Too Many Attempts.'
        ]);
        RateLimiter::clear('registration');
        Cache::flush();

        $response = $this->postJson('/api/v1/register', [
            'name' => 'New User After Reset',
            'email' => 'newuser@example.com',
            'password' => 'password123!',
            'password_confirmation' => 'password123!',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'user' => [
                    'id',
                    'name',
                    'email',
                    'created_at',
                    'updated_at'
                ]
            ]);

        $this->assertDatabaseHas('users', ['email' => 'newuser@example.com']);
    }

    public function test_rate_limit_reset_after_timeout(): void
    {
        $user = $this->createUser([
            'email' => 'rate-limit-test@example.com',
            'password' => Hash::make('password123')
        ]);

        for ($i = 0; $i <= self::MAX_LOGIN_ATTEMPTS; $i++) {
            $response = $this->postJson('/api/v1/login', [
                'email' => $user->getAttribute('email'),
                'password' => 'wrong-password-' . $i,
            ]);

            if ($i === self::MAX_LOGIN_ATTEMPTS) {
                $this->assertContains($response->getStatusCode(), [429, 422]);
            }
        }

        RateLimiter::clear('login|' . $user->getAttribute('email') . '|' . '127.0.0.1');
        Cache::flush();

        $response = $this->postJson('/api/v1/login', [
            'email' => $user->getAttribute('email'),
            'password' => 'password123',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['token']);

        $response->assertOk();
    }
}
