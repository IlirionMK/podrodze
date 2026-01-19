<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Laravel\Socialite\Two\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;
use Mockery;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase\ApiTestCase;

#[Group('authentication')]
#[Group('oauth')]
#[Group('facebook')]
class FacebookOAuthTest extends ApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->mockSocialite();
    }

    protected function mockSocialite(array $userData = []): void
    {
        $user = new SocialiteUser();
        $user->id = $userData['id'] ?? 'facebook-test-id-123';
        $user->name = $userData['name'] ?? 'Test User';
        $user->email = $userData['email'] ?? 'test@example.com';
        $user->token = $userData['token'] ?? 'test-oauth-token';
        $user->refreshToken = $userData['refresh_token'] ?? 'test-refresh-token';
        $user->expiresIn = $userData['expires_in'] ?? 3600;
        $user->user = array_merge([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
        ], $userData['user'] ?? []);

        $mockSocialite = Mockery::mock('Laravel\Socialite\Contracts\Factory');
        $provider = Mockery::mock('Laravel\Socialite\Two\FacebookProvider');

        $provider->shouldReceive('stateless')->andReturnSelf();

        $provider->shouldReceive('fields')
            ->withAnyArgs()
            ->andReturnSelf();

        $provider->shouldReceive('getAccessTokenResponse')
            ->withAnyArgs()
            ->andReturn([
                'access_token' => $user->token,
                'expires_in' => $user->expiresIn,
                'token_type' => 'Bearer',
            ]);

        $provider->shouldReceive('userFromToken')
            ->withAnyArgs()
            ->andReturn($user);

        $mockSocialite->shouldReceive('driver')
            ->withAnyArgs()
            ->andReturn($provider);

        Socialite::swap($mockSocialite);
    }

    public function test_facebook_oauth_callback_creates_new_user(): void
    {
        $this->mockSocialite([
            'email' => 'test@example.com',
            'name' => 'Test User',
            'id' => 'facebook-test-id-123',
            'token' => 'test-oauth-token',
            'refresh_token' => 'test-refresh-token',
            'expires_in' => 3600,
        ]);

        $response = $this->postJson($this->apiUrl . '/auth/facebook/callback', [
            'code' => 'test-auth-code',
        ]);

        if ($response->status() !== 200) {
            dump('Response status: ' . $response->status());
            dump('Response content: ' . $response->getContent());
        }

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'user' => ['id', 'name', 'email'],
            'token',
        ]);

        $user = User::where('email', 'test@example.com')->first();
        $this->assertNotNull($user);
        $this->assertEquals('Test User', $user->name);
    }

    public function test_facebook_oauth_links_to_existing_account(): void
    {
        $user = $this->createUser([
            'email' => 'existing@example.com',
        ]);

        $this->mockSocialite([
            'email' => 'existing@example.com',
            'name' => 'Existing User',
            'id' => 'facebook-test-id-456',
            'token' => 'test-oauth-token',
            'refresh_token' => 'test-refresh-token',
            'expires_in' => 3600,
        ]);

        $response = $this->postJson($this->apiUrl . '/auth/facebook/callback', [
            'code' => 'test-auth-code',
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('users', [
            'email' => 'existing@example.com',
            'facebook_id' => 'facebook-test-id-456'
        ]);
    }

    public function test_facebook_oauth_returns_error_for_invalid_code(): void
    {
        $mockSocialite = Mockery::mock('Laravel\Socialite\Contracts\Factory');
        $provider = Mockery::mock('Laravel\Socialite\Two\FacebookProvider');

        $provider->shouldReceive('stateless')->andReturnSelf();
        $provider->shouldReceive('getAccessTokenResponse')
            ->with('invalid-auth-code')
            ->andThrow(new \Exception('Invalid code'));

        $mockSocialite->shouldReceive('driver')
            ->with('facebook')
            ->andReturn($provider);

        Socialite::swap($mockSocialite);

        $response = $this->postJson($this->apiUrl . '/auth/facebook/callback', [
            'code' => 'invalid-auth-code',
        ]);

        $response->assertStatus(422);
        $response->assertJsonStructure(['message', 'error']);
    }

    public function test_facebook_oauth_requires_code_parameter(): void
    {
        $response = $this->postJson($this->apiUrl . '/auth/facebook/callback', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['code']);
    }

    public function test_facebook_oauth_handles_missing_email(): void
    {
        $mockSocialite = Mockery::mock('Laravel\Socialite\Contracts\Factory');
        $provider = Mockery::mock('Laravel\Socialite\Two\FacebookProvider');

        $provider->shouldReceive('stateless')->andReturnSelf();

        $provider->shouldReceive('fields')
            ->with(['id', 'name', 'email'])
            ->andReturnSelf();

        $provider->shouldReceive('getAccessTokenResponse')
            ->with('test-auth-code')
            ->andReturn([
                'access_token' => 'test-token',
                'expires_in' => 3600,
                'token_type' => 'Bearer',
            ]);

        $user = new SocialiteUser();
        $user->id = 'facebook-test-id-789';
        $user->name = 'No Email User';
        $user->email = null;
        $user->token = 'test-token';
        $user->user = [
            'id' => $user->id,
            'name' => $user->name,
            'email' => null
        ];

        $provider->shouldReceive('userFromToken')
            ->with('test-token')
            ->andReturn($user);

        $mockSocialite->shouldReceive('driver')
            ->with('facebook')
            ->andReturn($provider);

        Socialite::swap($mockSocialite);

        $response = $this->postJson($this->apiUrl . '/auth/facebook/callback', [
            'code' => 'test-auth-code',
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'message' => 'Facebook OAuth failed',
            'error' => 'facebook_email_missing'
        ]);
    }
}
