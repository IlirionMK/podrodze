<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Two\FacebookProvider;
use Laravel\Socialite\Two\User as SocialiteUserStub;
use Laravel\Socialite\Facades\Socialite;
use Mockery;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase\ApiTestCase;

#[Group('authentication')]
#[Group('oauth')]
#[Group('facebook')]
class FacebookOAuthTest extends ApiTestCase
{
    protected function mockSocialiteUser(string $email, string $name, string $id): void
    {
        $user = new SocialiteUserStub();
        $user->id = $id;
        $user->name = $name;
        $user->email = $email;
        $user->token = 'test-oauth-token';
        $user->refreshToken = 'test-refresh-token';
        $user->expiresIn = 3600;
        $user->user = [
            'id' => $id,
            'name' => $name,
            'email' => $email,
        ];

        $mockSocialite = Mockery::mock('Laravel\Socialite\Contracts\Factory');
        $provider = Mockery::mock('Laravel\Socialite\Two\FacebookProvider');

        $mockSocialite->shouldReceive('driver')
            ->with('facebook')
            ->andReturn($provider);

        $provider->shouldReceive('stateless')
            ->andReturnSelf();

        $provider->shouldReceive('getAccessTokenResponse')
            ->with('test-auth-code')
            ->andReturn([
                'access_token' => 'test-access-token',
                'expires_in' => 3600,
                'token_type' => 'Bearer',
            ]);

        $provider->shouldReceive('userFromToken')
            ->with('test-access-token')
            ->andReturn($user);

        $provider->shouldReceive('user')
            ->andReturn($user);

        Socialite::shouldReceive('driver')
            ->with('facebook')
            ->andReturn($provider);
    }

    public function test_get_facebook_auth_url_returns_valid_url(): void
    {
        $response = $this->getJson($this->apiUrl . '/auth/facebook/url');

        $response->assertStatus(200);
        $response->assertJsonStructure(['url']);
        $this->assertStringStartsWith('https://www.facebook.com/v', $response->json('url'));
    }

    public function test_facebook_oauth_callback_creates_new_user(): void
    {
        $user = new SocialiteUserStub();
        $user->id = 'facebook-test-id-123';
        $user->name = 'Test User';
        $user->email = 'test@example.com';
        $user->token = 'test-oauth-token';
        $user->refreshToken = 'test-refresh-token';
        $user->expiresIn = 3600;
        $user->user = [
            'id' => 'facebook-test-id-123',
            'name' => 'Test User',
            'email' => 'test@example.com',
        ];

        $mockSocialite = Mockery::mock('Laravel\Socialite\Contracts\Factory');
        $provider = Mockery::mock('Laravel\Socialite\Two\FacebookProvider');

        $mockSocialite->shouldReceive('driver')
            ->with('facebook')
            ->andReturn($provider);

        $provider->shouldReceive('stateless')
            ->andReturnSelf();

        $provider->shouldReceive('fields')
            ->with(['id', 'name', 'email'])
            ->andReturnSelf();

        $provider->shouldReceive('getAccessTokenResponse')
            ->with('test-auth-code')
            ->andReturn([
                'access_token' => 'test-access-token',
                'expires_in' => 3600,
                'token_type' => 'Bearer',
            ]);

        $provider->shouldReceive('userFromToken')
            ->with('test-access-token')
            ->andReturn($user);

        $provider->shouldReceive('user')
            ->andReturn($user);

        Socialite::shouldReceive('driver')
            ->with('facebook')
            ->andReturn($provider);

        $response = $this->postJson($this->apiUrl . '/auth/facebook/callback', [
            'code' => 'test-auth-code',
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'user' => ['id', 'name', 'email'],
            'token',
        ]);

        $this->assertDatabaseHas('users', [
            'email' => 'test@example.com',
            'facebook_id' => 'facebook-test-id-123',
            'name' => 'Test User',
        ]);
    }

    public function test_facebook_oauth_links_to_existing_account(): void
    {
        $user = $this->createUser([
            'email' => 'existing@example.com',
            'facebook_id' => null,
        ]);

        $socialiteUser = new SocialiteUserStub();
        $socialiteUser->id = 'facebook-test-id-456';
        $socialiteUser->name = 'Existing User';
        $socialiteUser->email = 'existing@example.com';
        $socialiteUser->token = 'test-oauth-token';
        $socialiteUser->refreshToken = 'test-refresh-token';
        $socialiteUser->expiresIn = 3600;
        $socialiteUser->user = [
            'id' => 'facebook-test-id-456',
            'name' => 'Existing User',
            'email' => 'existing@example.com',
        ];

        $mockSocialite = Mockery::mock('Laravel\Socialite\Contracts\Factory');
        $provider = Mockery::mock('Laravel\Socialite\Two\FacebookProvider');

        $mockSocialite->shouldReceive('driver')
            ->with('facebook')
            ->andReturn($provider);

        $provider->shouldReceive('stateless')
            ->andReturnSelf();

        $provider->shouldReceive('fields')
            ->with(['id', 'name', 'email'])
            ->andReturnSelf();

        $provider->shouldReceive('getAccessTokenResponse')
            ->with('test-auth-code')
            ->andReturn([
                'access_token' => 'test-access-token',
                'expires_in' => 3600,
                'token_type' => 'Bearer',
            ]);

        $provider->shouldReceive('userFromToken')
            ->with('test-access-token')
            ->andReturn($socialiteUser);

        $provider->shouldReceive('user')
            ->andReturn($socialiteUser);

        Socialite::shouldReceive('driver')
            ->with('facebook')
            ->andReturn($provider);

        $response = $this->postJson($this->apiUrl . '/auth/facebook/callback', [
            'code' => 'test-auth-code',
        ]);

        $response->assertStatus(200);

        $user->refresh();

        $this->assertEquals('facebook-test-id-456', $user->facebook_id);
        $this->assertEquals('existing@example.com', $user->email);
    }

    public function test_facebook_oauth_returns_error_for_invalid_code(): void
    {
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
        $user = new SocialiteUserStub();
        $user->id = 'facebook-test-id-789';
        $user->name = 'No Email User';
        $user->email = null;
        $user->token = 'test-oauth-token';
        $user->refreshToken = 'test-refresh-token';
        $user->expiresIn = 3600;
        $user->user = [
            'id' => 'facebook-test-id-789',
            'name' => 'No Email User',
        ];

        $mockSocialite = Mockery::mock('Laravel\Socialite\Contracts\Factory');
        $provider = Mockery::mock('Laravel\Socialite\Two\FacebookProvider');

        $mockSocialite->shouldReceive('driver')
            ->with('facebook')
            ->andReturn($provider);

        $provider->shouldReceive('stateless')
            ->andReturnSelf();

        $provider->shouldReceive('fields')
            ->with(['id', 'name', 'email'])
            ->andReturnSelf();

        $provider->shouldReceive('getAccessTokenResponse')
            ->with('test-auth-code')
            ->andReturn([
                'access_token' => 'test-access-token',
                'expires_in' => 3600,
                'token_type' => 'Bearer',
            ]);

        $provider->shouldReceive('userFromToken')
            ->with('test-access-token')
            ->andReturn($user);

        $provider->shouldReceive('user')
            ->andReturn($user);

        Socialite::shouldReceive('driver')
            ->with('facebook')
            ->andReturn($provider);

        $response = $this->postJson($this->apiUrl . '/auth/facebook/callback', [
            'code' => 'test-auth-code',
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'message' => 'Facebook OAuth failed',
            'error' => 'facebook_email_missing',
        ]);
    }
}
