<?php

declare(strict_types=1);

namespace Tests\Feature\E2E;

use App\Models\User;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase\ApiTestCase;

/**
 * End-to-end tests for social authentication flows.
 *
 * This test verifies the complete social authentication journey including:
 * - Google OAuth sign-up and login
 * - Facebook OAuth sign-up and login
 * - Account linking between email and social providers
 * - Handling of existing accounts during social login
 *
 * @covers \App\Http\Controllers\Auth\{
 *     GoogleAuthController,
 *     FacebookAuthController,
 *     SocialAccountController
 * }
 */
#[Group('authentication')]
#[Group('oauth')]
#[Group('e2e')]
class SocialAuthE2ETest extends ApiTestCase
{
    private const TEST_EMAIL = 'social.user@example.com';
    private const TEST_PASSWORD = 'SecurePass123!';
    private const TEST_NAME = 'Social User';

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockSocialite();
    }

    protected function mockSocialite(array $userData = [], string $provider = 'google'): void
    {
        $defaults = [
            'id' => $provider . '-test-id-' . uniqid(),
            'name' => self::TEST_NAME,
            'email' => self::TEST_EMAIL,
            'token' => 'test-oauth-token-' . uniqid(),
            'refresh_token' => 'test-refresh-token-' . uniqid(),
            'expires_in' => 3600,
        ];

        $userData = array_merge($defaults, $userData);

        $user = new SocialiteUser();
        $user->id = $userData['id'];
        $user->name = $userData['name'];
        $user->email = $userData['email'];
        $user->token = $userData['token'];
        $user->refreshToken = $userData['refresh_token'];
        $user->expiresIn = $userData['expires_in'];

        $user->user = array_merge([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
        ], $userData['user'] ?? []);

        $tokenResponse = [
            'access_token' => $user->token,
            'expires_in' => $user->expiresIn,
            'refresh_token' => $user->refreshToken,
            'token_type' => 'Bearer',
        ];

        $providerClass = 'Laravel\\Socialite\\Two\\' . ucfirst($provider) . 'Provider';
        $mockProvider = Mockery::mock($providerClass);

        $mockProvider->shouldReceive('stateless')
            ->andReturnSelf();

        $mockProvider->shouldReceive('getAccessTokenResponse')
            ->with('test-auth-code-123')
            ->andReturn($tokenResponse);

        if ($provider === 'google') {
            $mockProvider->shouldReceive('userFromToken')
                ->with($user->token)
                ->andReturn($user);
        } else if ($provider === 'facebook') {
            $mockProvider->shouldReceive('fields')
                ->with(Mockery::on(function($fields) {
                    return is_array($fields) &&
                           in_array('id', $fields) &&
                           in_array('name', $fields) &&
                           in_array('email', $fields);
                }))
                ->andReturnSelf();

            $mockProvider->shouldReceive('userFromToken')
                ->with($user->token)
                ->andReturn($user);
        }

        $mockProvider->shouldReceive('user')
            ->andReturnUsing(function () use ($user) {
                return $user;
            });

        Socialite::shouldReceive('driver')
            ->with($provider)
            ->andReturn($mockProvider);
    }

    public function test_google_oauth_signup_and_login(): void
    {
        $this->mockSocialite([], 'google');

        $response = $this->postJson('/api/v1/auth/google/callback', [
            'code' => 'test-auth-code-123',
        ]);

        if ($response->status() !== 200) {
            dump('Google OAuth response:', $response->getContent());
        }

        $response->assertStatus(200)
            ->assertJsonStructure([
                'user' => [
                    'id',
                    'name',
                    'email',
                ],
                'token'
            ]);

        $user = User::where('email', self::TEST_EMAIL)->first();
        $this->assertNotNull($user);
        $this->assertEquals(self::TEST_NAME, $user->name);
        $this->assertStringStartsWith('google-test-id-', $user->google_id);

        $response = $this->postJson('/api/v1/auth/google/callback', [
            'code' => 'test-auth-code-123',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'user' => [
                    'email' => self::TEST_EMAIL,
                    'name' => self::TEST_NAME,
                ]
            ]);
    }

    public function test_facebook_oauth_signup_and_login(): void
    {
        $this->mockSocialite([], 'facebook');

        $response = $this->postJson('/api/v1/auth/facebook/callback', [
            'code' => 'test-auth-code-123',
        ]);

        if ($response->status() !== 200) {
            dump('Facebook OAuth response:', $response->getContent());
        }

        $response->assertStatus(200)
            ->assertJsonStructure([
                'user' => [
                    'id',
                    'name',
                    'email',
                ],
                'token'
            ]);

        $user = User::where('email', self::TEST_EMAIL)->first();
        $this->assertNotNull($user);
        $this->assertEquals(self::TEST_NAME, $user->name);
        $this->assertStringStartsWith('facebook-test-id-', $user->facebook_id);

        $response = $this->postJson('/api/v1/auth/facebook/callback', [
            'code' => 'test-auth-code-123',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'user' => [
                    'email' => self::TEST_EMAIL,
                    'name' => self::TEST_NAME,
                ]
            ]);
    }

    public function test_account_linking(): void
    {
        $user = User::factory()->create([
            'email' => self::TEST_EMAIL,
            'name' => self::TEST_NAME,
            'password' => bcrypt(self::TEST_PASSWORD),
            'google_id' => null,
        ]);

        $this->mockSocialite([
            'email' => self::TEST_EMAIL,
        ], 'google');

        $response = $this->postJson('/api/v1/auth/google/callback', [
            'code' => 'test-auth-code-123',
        ]);

        if ($response->status() !== 200) {
            dump('Account linking response:', $response->getContent());
        }

        $response->assertStatus(200)
            ->assertJson([
                'user' => [
                    'email' => self::TEST_EMAIL,
                    'name' => self::TEST_NAME,
                ]
            ]);

        $user->refresh();
        $this->assertStringStartsWith('google-test-id-', $user->google_id);
    }

    public function test_handling_existing_account_during_social_login(): void
    {
        $googleId = 'existing-google-id-123';
        $user = User::factory()->create([
            'email' => self::TEST_EMAIL,
            'name' => self::TEST_NAME,
            'google_id' => $googleId,
        ]);

        $this->mockSocialite([
            'id' => $googleId,
            'email' => 'new-email@example.com',
        ], 'google');

        $response = $this->postJson('/api/v1/auth/google/callback', [
            'code' => 'test-auth-code-123',
        ]);

        if ($response->status() !== 200) {
            dump('Existing account handling response:', $response->getContent());
        }

        $response->assertStatus(200)
            ->assertJson([
                'user' => [
                    'email' => self::TEST_EMAIL,
                    'name' => self::TEST_NAME,
                ]
            ]);

        $this->assertDatabaseMissing('users', [
            'email' => 'new-email@example.com',
        ]);

        $user->refresh();
        $this->assertEquals($googleId, $user->google_id);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        Mockery::close();
    }
}
