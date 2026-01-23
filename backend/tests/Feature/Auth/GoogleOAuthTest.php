<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Laravel\Socialite\Two\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;
use Mockery;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase\ApiTestCase;

/**
 * Tests for Google OAuth authentication flow.
 *
 * This class verifies that:
 * - Users can authenticate using Google OAuth
 * - Existing users can link their accounts with Google
 * - Error cases are properly handled (invalid codes, missing emails)
 * - User data is properly synchronized with Google
 *
 * @covers \App\Http\Controllers\Auth\Auth\GoogleAuthController
 */

#[Group('authentication')]
#[Group('oauth')]
#[Group('google')]
class GoogleOAuthTest extends ApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->mockSocialite();
    }

    protected function mockSocialite(array $userData = []): void
    {
        $user = new SocialiteUser();
        $user->id = $userData['id'] ?? 'google-test-id-123';
        $user->name = $userData['name'] ?? 'Test User';
        $user->email = $userData['email'] ?? 'test@example.com';
        $user->token = $userData['token'] ?? 'test-oauth-token';
        $user->refreshToken = $userData['refresh_token'] ?? 'test-refresh-token';
        $user->expiresIn = $userData['expires_in'] ?? 3600;
        $user->user = array_merge([
            'sub' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
        ], $userData['user'] ?? []);

        $mockSocialite = Mockery::mock('Laravel\Socialite\Contracts\Factory');
        $provider = Mockery::mock('Laravel\Socialite\Two\GoogleProvider');

        $provider->shouldReceive('stateless')->andReturnSelf();

        $provider->shouldReceive('getAccessTokenResponse')
            ->with('test-auth-code')
            ->andReturn([
                'access_token' => $user->token,
                'expires_in' => $user->expiresIn,
                'token_type' => 'Bearer',
            ]);

        $provider->shouldReceive('userFromToken')
            ->with($user->token)
            ->andReturn($user);

        $mockSocialite->shouldReceive('driver')
            ->with('google')
            ->andReturn($provider);

        Socialite::swap($mockSocialite);
    }

    public function test_google_oauth_callback_creates_new_user(): void
    {
        $response = $this->postJson($this->apiUrl . '/auth/google/callback', [
            'code' => 'test-auth-code',
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'user' => ['id', 'name', 'email'],
            'token',
        ]);

        $user = User::where('email', 'test@example.com')->first();
        $this->assertNotNull($user);
        $this->assertEquals('Test User', $user->name);
    }

    public function test_google_oauth_links_to_existing_account(): void
    {
        $user = $this->createUser([
            'email' => 'existing@example.com',
        ]);

        $this->mockSocialite([
            'email' => 'existing@example.com',
            'name' => 'Existing User',
            'id' => 'google-test-id-456',
        ]);

        $response = $this->postJson($this->apiUrl . '/auth/google/callback', [
            'code' => 'test-auth-code',
        ]);

        $response->assertStatus(200);
        $user->refresh();
        $this->assertEquals('existing@example.com', $user->email);
    }

    public function test_google_oauth_returns_error_for_invalid_code(): void
    {
        $response = $this->postJson($this->apiUrl . '/auth/google/callback', [
            'code' => 'invalid-auth-code',
        ]);

        $response->assertStatus(422);
        $response->assertJsonStructure(['message', 'error']);
    }

    public function test_google_oauth_requires_code_parameter(): void
    {
        $response = $this->postJson($this->apiUrl . '/auth/google/callback', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['code']);
    }

    public function test_google_oauth_handles_missing_email(): void
    {
        $mockSocialite = Mockery::mock('Laravel\Socialite\Contracts\Factory');
        $provider = Mockery::mock('Laravel\Socialite\Two\GoogleProvider');

        $provider->shouldReceive('stateless')->andReturnSelf();

        $provider->shouldReceive('getAccessTokenResponse')
            ->with('test-auth-code')
            ->andReturn([
                'access_token' => 'test-token',
                'expires_in' => 3600,
                'token_type' => 'Bearer',
            ]);

        $user = new SocialiteUser();
        $user->email = null;
        $user->name = 'No Email User';
        $user->id = 'google-test-id-789';
        $user->user = ['email' => null];

        $provider->shouldReceive('userFromToken')
            ->with('test-token')
            ->andReturn($user);

        $mockSocialite->shouldReceive('driver')
            ->with('google')
            ->andReturn($provider);

        Socialite::swap($mockSocialite);

        $response = $this->postJson($this->apiUrl . '/auth/google/callback', [
            'code' => 'test-auth-code',
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'message' => 'Google OAuth failed',
            'error' => 'Google account does not provide an email address.'
        ]);
    }
}
