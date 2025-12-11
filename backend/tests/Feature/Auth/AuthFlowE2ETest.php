<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * End-to-end tests for the complete authentication flow.
 *
 * This class contains tests for:
 * - Complete user registration and login flow
 * - Email verification requirements
 * - Session management
 * - Token-based authentication
 * - User data persistence
 */
#[Group('auth')]
#[Group('e2e')]
#[Group('authentication')]
class AuthFlowE2ETest extends TestCase
{
    use RefreshDatabase;

    // E2E W.I.P
    public function test_complete_authentication_flow(): void
    {
        $userData = [
            'name' => 'Jan Kowalski',
            'email' => 'jan.kowalski@example.com',
            'password' => 'zaq1@WSX',
            'password_confirmation' => 'zaq1@WSX',
        ];

        $registerResponse = $this->postJson('/api/v1/register', $userData);

        $registerResponse->assertStatus(201)
            ->assertJsonStructure([
                'user' => [
                    'id',
                    'name',
                    'email',
                    'created_at',
                    'updated_at'
                ],
                'token'
            ]);

        $token = $registerResponse->json('token');

        $this->assertDatabaseHas('users', [
            'name' => $userData['name'],
            'email' => $userData['email']
        ]);

        $loginResponse = $this->postJson('/api/v1/login', [
            'email' => $userData['email'],
            'password' => $userData['password'],
        ]);

        $loginResponse->assertStatus(403)
            ->assertJson([
                'message' => 'Your email address is not verified.'
            ]);

        $user = User::where('email', $userData['email'])->first();
        $user->email_verified_at = now();
        $user->save();

        $loginResponse = $this->postJson('/api/v1/login', [
            'email' => $userData['email'],
            'password' => $userData['password'],
        ]);

        $loginResponse->assertStatus(200)
            ->assertJsonStructure([
                'user' => [
                    'id',
                    'name',
                    'email',
                    'email_verified_at',
                    'created_at',
                    'updated_at'
                ],
                'token'
            ]);

        $token = $loginResponse->json('token');

        $logoutResponse = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
        ])->postJson('/api/v1/logout');

        $logoutResponse->assertNoContent();

        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'tokenable_type' => get_class($user),
        ]);

        $protectedResponse = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
        ])->getJson('/api/v1/user');

        $protectedResponse->assertStatus(401);
    }
}
