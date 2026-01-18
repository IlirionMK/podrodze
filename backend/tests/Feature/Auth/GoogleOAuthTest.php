<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Two\GoogleProvider;
use Laravel\Socialite\Two\User as SocialiteUserStub;
use Laravel\Socialite\Facades\Socialite;
use Mockery;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase\ApiTestCase;

#[Group('authentication')]
#[Group('oauth')]
#[Group('google')]
class GoogleOAuthTest extends ApiTestCase
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
            'sub' => $id,
            'name' => $name,
            'email' => $email,
        ];

        $mockSocialite = Mockery::mock('Laravel\Socialite\Contracts\Factory');
        $provider = Mockery::mock('Laravel\Socialite\Two\GoogleProvider');

        $mockSocialite->shouldReceive('driver')
            ->with('google')
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

        Socialite::swap($mockSocialite);
    }

    public function test_get_google_auth_url_returns_valid_url(): void
    {
        $response = $this->getJson($this->apiUrl . '/auth/google/url');

        $response->assertStatus(200);
        $response->assertJsonStructure(['url']);
        $this->assertStringStartsWith('https://accounts.google.com/o/oauth2/auth', $response->json('url'));
    }

    public function test_google_oauth_callback_creates_new_user(): void
    {
        $this->mockSocialiteUser(
            'test@example.com',
            'Test User',
            'google-test-id-123'
        );

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
        $this->assertEquals('google-test-id-123', $user->google_id);
        $this->assertEquals('Test User', $user->name);
    }

    public function test_google_oauth_links_to_existing_account(): void
    {
        $user = $this->createUser([
            'email' => 'existing@example.com',
            'google_id' => null,
        ]);

        $this->mockSocialiteUser(
            'existing@example.com',
            'Existing User',
            'google-test-id-456'
        );

        $response = $this->postJson($this->apiUrl . '/auth/google/callback', [
            'code' => 'test-auth-code',
        ]);

        $response->assertStatus(200);

        $user->refresh();

        $this->assertEquals('google-test-id-456', $user->google_id);
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
        $user = new SocialiteUserStub();
        $user->id = 'google-test-id-789';
        $user->name = 'No Email User';
        $user->email = null;
        $user->token = 'test-oauth-token';
        $user->refreshToken = 'test-refresh-token';
        $user->expiresIn = 3600;
        $user->user = [
            'sub' => 'google-test-id-789',
            'name' => 'No Email User',
            'email' => null,
        ];

        $mockSocialite = Mockery::mock('Laravel\Socialite\Contracts\Factory');
        $provider = Mockery::mock('Laravel\Socialite\Two\GoogleProvider');

        $mockSocialite->shouldReceive('driver')
            ->with('google')
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
