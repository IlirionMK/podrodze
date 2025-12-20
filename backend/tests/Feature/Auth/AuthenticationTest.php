<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase\ApiTestCase;

/**
 * Integration tests for the user authentication process.
 */
#[Group('authentication')]
#[Group('auth')]
class AuthenticationTest extends ApiTestCase
{
    protected bool $enableRateLimiting = true;
    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $user = $this->createUser(['password' => 'password']);

        $response = $this->apiRequest('POST', '/login', [
            'email' => $user->getAttribute('email'),
            'password' => 'password',
        ]);

        $this->assertSuccessResponse($response);
        $response->assertJsonStructure([
            'user'  => ['id', 'name', 'email'],
            'token',
        ]);

        $response->assertJson([
            'user' => [
                'id' => $user->getKey(),
                'email' => $user->getAttribute('email'),
            ],
            'token' => true,
        ]);

        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $user->getKey(),
            'tokenable_type' => User::class,
        ]);
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = $this->createUser(['password' => 'password']);

        $response = $this->apiRequest('POST', '/login', [
            'email' => $user->getAttribute('email'),
            'password' => 'wrong-password',
        ]);

        $this->assertUnprocessableResponse($response);
        $response->assertJson(['message' => 'invalid_credentials']);
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->assertGuest();
    }

    public function test_login_fails_with_empty_credentials(): void
    {
        $response = $this->apiRequest('POST', '/login', [
            'email' => '',
            'password' => '',
        ]);

        $this->assertUnprocessableResponse($response);
        $response->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_login_fails_with_nonexistent_email(): void
    {
        $response = $this->apiRequest('POST', '/login', [
            'email' => 'nonexistent@example.com',
            'password' => 'password',
        ]);

        $this->assertUnprocessableResponse($response);
        $response->assertJson(['message' => 'invalid_credentials']);
        $this->assertGuest();
    }

    public function test_login_fails_with_invalid_email_format(): void
    {
        $response = $this->apiRequest('POST', '/login', [
            'email' => 'invalid-email',
            'password' => 'password',
        ]);

        $this->assertUnprocessableResponse($response);
        $response->assertJsonValidationErrors(['email']);
    }

    public function test_users_can_logout(): void
    {
        $user = $this->createUser();
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
        ])->postJson('/api/v1/logout');

        $this->assertNoContentResponse($response);
        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $user->getAttribute('id'),
            'tokenable_type' => get_class($user),
        ]);
    }

    public function test_unauthenticated_users_cannot_access_protected_routes(): void
    {
        $response = $this->apiRequest('GET', '/user');
        $this->assertUnauthorizedResponse($response);
    }

    public function test_login_is_case_sensitive_for_email(): void
    {
        $user = $this->createUser([
            'email' => 'Test@Example.com',
            'password' => 'password'
        ]);

        $response = $this->apiRequest('POST', '/login', [
            'email' => 'test@example.com',
            'password' => 'password',
        ]);

        $this->assertUnprocessableResponse($response);
        $response->assertJson(['message' => 'invalid_credentials']);

        $response = $this->apiRequest('POST', '/login', [
            'email' => 'Test@Example.com',
            'password' => 'password',
        ]);

        $this->assertSuccessResponse($response);

        $response->assertJson([
            'user' => [
                'id' => $user->getKey(),
                'email' => $user->getAttribute('email'),
            ],
            'token' => true,
        ]);

        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $user->getKey(),
            'tokenable_type' => User::class,
        ]);
    }
}
