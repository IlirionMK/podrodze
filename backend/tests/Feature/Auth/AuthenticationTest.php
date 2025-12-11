<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * Integration tests for the user authentication process.
 *
 * This class contains tests for:
 * - User login functionality (successful login, response structure)
 * - Login data validation (invalid credentials, empty fields, email format)
 * - Logout mechanism (token revocation, session handling)
 * - Access token management (token creation, validation, revocation)
 * - Edge case handling (case sensitivity, non-existent users)
 * - Authentication security (token management, session security)
 *
 * NOTE: This is a work in progress. The test suite is currently being developed
 * and may be subject to changes and additions.
 */
#[Group('authentication')]
#[Group('auth')]
class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $user = User::factory()->create(['password' => bcrypt('password')]);

        $response = $this->postJson('/api/v1/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response
            ->assertOk()
            ->assertJsonStructure([
                'user'  => ['id', 'name', 'email'],
                'token',
            ]);

        $this->assertAuthenticatedAs($user);

        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id'   => $user->id,
            'tokenable_type' => \App\Models\User::class,
        ]);
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create(['password' => bcrypt('password')]);

        $response = $this->postJson('/api/v1/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(422)
            ->assertJson(['message' => 'invalid_credentials']);

        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->assertGuest(); // Upewniamy się, że użytkownik nie jest zalogowany
    }

    public function test_login_fails_with_empty_credentials(): void
    {
        $response = $this->postJson('/api/v1/login', [
            'email' => '',
            'password' => '',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_login_fails_with_nonexistent_email(): void
    {
        $response = $this->postJson('/api/v1/login', [
            'email' => 'nonexistent@example.com',
            'password' => 'password',
        ]);

        $response->assertStatus(422)
            ->assertJson(['message' => 'invalid_credentials']);

        $this->assertGuest();
    }

    public function test_login_fails_with_invalid_email_format(): void
    {
        $response = $this->postJson('/api/v1/login', [
            'email' => 'invalid-email',
            'password' => 'password',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_login_fails_without_password(): void
    {
        $user = User::factory()->create();

        $response = $this->postJson('/api/v1/login', [
            'email' => $user->email,
            'password' => '',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    public function test_logout_fails_without_token(): void
    {
        $response = $this->postJson('/api/v1/logout');
        $response->assertStatus(401);
    }

    public function test_api_login_returns_user_data_and_token(): void
    {
        $user = User::factory()->create(['password' => bcrypt('password')]);

        $response = $this->postJson('/api/v1/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'user' => ['id', 'name', 'email', 'email_verified_at', 'created_at', 'updated_at'],
                'token'
            ]);

        $this->assertAuthenticatedAs($user);
    }

    public function test_web_login_returns_redirect_response(): void
    {
        $user = User::factory()->create(['password' => bcrypt('password')]);

        $response = $this->post('/api/v1/login', [
            'email' => $user->email,
            'password' => 'password',
        ], ['Accept' => 'text/html']);

        // Verify it returns user data and token
        $response->assertStatus(200)
            ->assertJsonStructure([
                'user' => ['id', 'name', 'email', 'email_verified_at', 'created_at', 'updated_at'],
                'token'
            ])
            ->assertHeader('access-control-allow-origin', 'http://localhost:5173')
            ->assertHeader('access-control-allow-credentials', 'true');
    }

    public function test_web_login_authenticates_user(): void
    {
        $user = User::factory()->create(['password' => bcrypt('password')]);

        $this->post('/api/v1/login', [
            'email' => $user->email,
            'password' => 'password',
        ], ['Accept' => 'text/html']);

        $this->assertAuthenticated();
        $this->assertEquals($user->id, auth()->id());
    }

    public function test_token_is_revoked_after_logout(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        $this->postJson('/api/v1/logout', [], [
            'Authorization' => "Bearer {$token}",
        ])->assertNoContent();

        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'name' => 'test-token'
        ]);
    }

    public function test_login_is_case_sensitive_for_email(): void
    {

        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password')
        ]);

        $response = $this->postJson('/api/v1/login', [
            'email' => 'TEST@example.com', // różna wielkość liter
            'password' => 'password',
        ]);

        $response->assertStatus(422)
            ->assertJson(['message' => 'invalid_credentials']);

        $this->assertGuest();
    }

    public function test_login_response_structure(): void
    {
        $user = User::factory()->create(['password' => bcrypt('password')]);

        $response = $this->postJson('/api/v1/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertJsonStructure([
            'user' => [
                'id',
                'name',
                'email',
                'email_verified_at',
                'created_at',
                'updated_at',
            ],
            'token'
        ]);
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();
        $plain = $user->createToken('test-token')->plainTextToken;

        $response = $this->postJson('/api/v1/logout', [], [
            'Authorization' => "Bearer {$plain}",
        ]);

        $response->assertNoContent();

        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id'   => $user->id,
            'tokenable_type' => \App\Models\User::class,
            'name'           => 'test-token',
        ]);
    }

    public function test_debug_login_responses(): void
    {
        // Test non-existent email
        $response = $this->postJson('/api/v1/login', [
            'email' => 'nonexistent@example.com',
            'password' => 'password',
        ]);
        echo "\n\n[DEBUG] Non-existent email response status: " . $response->status();
        echo "\n[DEBUG] Non-existent email response content: " . $response->content();

        // Test case sensitivity
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password')
        ]);

        $response = $this->postJson('/api/v1/login', [
            'email' => 'TEST@example.com',
            'password' => 'password',
        ]);
        echo "\n\n[DEBUG] Case sensitivity response status: " . $response->status();
        echo "\n[DEBUG] Case sensitivity response content: " . $response->content();

        $response1 = $this->postJson('/api/v1/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);
        echo "\n\n[DEBUG] First login status: " . $response1->status();
        echo "\n[DEBUG] First login content: " . $response1->content();

        $response2 = $this->postJson('/api/v1/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);
        echo "\n\n[DEBUG] Second login status: " . $response2->status();
        echo "\n[DEBUG] Second login content: " . $response2->content();

        $this->assertTrue(true);
    }
}
