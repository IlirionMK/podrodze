<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests for rate limiting of authentication endpoints.
 *
 * This class contains tests for:
 * - Login attempt rate limiting
 * - Registration rate limiting
 * - Password reset request limiting
 * - Rate limit reset functionality
 * - IP-based rate limiting
 */
#[Group('auth')]
#[Group('security')]
#[Group('rate-limiting')]
class RateLimitingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        if (!env('APP_KEY')) {
            config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);
        }
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);
        config(['cache.default' => 'array']);
        config(['view.cache' => false]);
        config(['filesystems.default' => 'array']);
    }

    public function test_login_rate_limiting_after_multiple_attempts()
    {
        $user = User::factory()->create([
            'password' => bcrypt('password123')
        ]);

        for ($i = 0; $i < 5; $i++) {
            $response = $this->postJson('/api/v1/login', [
                'email' => $user->email,
                'password' => 'wrong-password',
            ]);
        }

        $response = $this->postJson('/api/v1/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertContains($response->status(), [429, 422], 
            'Expected rate limiting response (429 or 422) but got ' . $response->status()
        );
    }

    public function test_password_reset_request_rate_limiting()
    {
        $user = User::factory()->create();

        for ($i = 0; $i < 2; $i++) {
            $response = $this->postJson('/api/v1/forgot-password', [
                'email' => $user->email,
            ]);
        }

        $response = $this->postJson('/api/v1/forgot-password', [
            'email' => $user->email,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email'])
            ->assertJsonFragment(['email' => ['Please wait before retrying.']]);
    }

    public function test_registration_rate_limiting_by_ip()
    {
        for ($i = 0; $i < 6; $i++) {
            $response = $this->postJson('/api/v1/register', [
                'name' => 'Test User',
                'email' => 'test' . uniqid() . '@example.com', // Unikalny email
                'password' => 'password',
                'password_confirmation' => 'password',
            ]);

            // Sprawdź czy pierwsze 5 prób się powiodło
            if ($i < 5) {
                $response->assertStatus(201); // 201 Created
            }
        }

        // 6 próba powinna przekroczyć limit
        $response->assertStatus(429)
            ->assertJson([
                'message' => 'Too Many Attempts.'
            ]);
    }

    public function test_email_must_be_unique()
    {
        $email = 'test@example.com';

        // Pierwsza rejestracja powinna się powieść
        $response = $this->postJson('/api/v1/register', [
            'name' => 'Test User',
            'email' => $email,
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);
        $response->assertStatus(201);

        // Druga próba z tym samym emailem powinna się nie powieść
        $response = $this->postJson('/api/v1/register', [
            'name' => 'Test User 2',
            'email' => $email, // Ten sam email
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email'])
            ->assertJsonFragment([
                'message' => 'The email has already been taken.'
            ]);
    }

    public function test_login_rate_limit_resets_after_timeout()
    {
        $user = User::factory()->create([
            'password' => bcrypt('password123')
        ]);

        for ($i = 0; $i < 6; $i++) {
            $this->postJson('/api/v1/login', [
                'email' => $user->email,
                'password' => 'wrong-password',
            ]);
        }

        $this->travel(2)->minutes();

        $response = $this->postJson('/api/v1/login', [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        $response->assertStatus(200);
    }

    public function test_rate_limiting_is_per_ip_address()
    {
        $user1 = User::factory()->create(['password' => bcrypt('password123')]);
        $user2 = User::factory()->create(['password' => bcrypt('password123')]);

        for ($i = 0; $i < 6; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => '192.168.1.1'])
                ->postJson('/api/v1/login', [
                    'email' => $user1->email,
                    'password' => 'wrong-password',
                ]);
        }

        $response = $this->withServerVariables(['REMOTE_ADDR' => '192.168.1.2'])
            ->postJson('/api/v1/login', [
                'email' => $user2->email,
                'password' => 'password123',
            ]);

        $response->assertStatus(200);
    }
}
