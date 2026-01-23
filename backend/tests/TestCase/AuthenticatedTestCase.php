<?php

declare(strict_types=1);

namespace Tests\TestCase;

use App\Models\User;
use Laravel\Sanctum\Sanctum;

/**
 * Base test case for authenticated API tests.
 *
 * This class provides authentication related functionality
 * and helper methods for testing authenticated routes.
 *
 * @package Tests\TestCase
 * @uses \Tests\TestCase\ApiTestCase
 * @property string $defaultPassword Default password for test users
 */
abstract class AuthenticatedTestCase extends ApiTestCase
{
    /**
     * Default password used for test users.
     */
    protected string $defaultPassword = 'password';

    /**
     * Create and authenticate a user with the given abilities.
     *
     * @param User|null $user The user to authenticate, or null to create a new one
     * @param array<string> $abilities Array of abilities to grant to the token
     * @return $this
     */
    /**
     * Create and authenticate a user with the given abilities.
     *
     * @param User|null $user The user to authenticate, or null to create a new one
     * @param array<string> $abilities Array of abilities to grant to the token
     * @param string $guard The authentication guard to use
     * @return $this
     */
    protected function actingAsUser(
        ?User $user = null,
        array $abilities = ['*'],
        string $guard = 'sanctum'
    ): self {
        $user = $user ?: $this->createUser();

        if ($guard === 'sanctum') {
            Sanctum::actingAs($user, $abilities, $guard);
        } else {
            $this->actingAs($user, $guard);
        }

        return $this;
    }

    /**
     * Create and authenticate an admin user.
     *
     * @param array<string> $abilities Additional abilities to grant to the admin token
     * @return $this
     */
    protected function actingAsAdmin(array $abilities = ['*']): self
    {
        $admin = User::factory()->create(['role' => 'admin']);
        return $this->actingAsUser($admin, array_merge(['admin'], $abilities));
    }

    /**
     * Create and authenticate a test user.
     *
     * @param User|null $user User to authenticate, or null to create a new one
     * @param array<string> $abilities Abilities to grant to the token
     * @return User The authenticated user
     */
    protected function login(?User $user = null, array $abilities = ['*']): User
    {
        $user = $user ?: $this->createUser();
        $this->actingAsUser($user, $abilities);
        return $user;
    }

    /**
     * Create a new user with the given attributes.
     *
     * @param array<string, mixed> $attributes User attributes
     * @param bool $verified Whether the user should be email-verified
     * @return User
     */
    /**
     * Create a new user with the given attributes.
     *
     * @param array<string, mixed> $attributes User attributes
     * @param bool $verified Whether the user should be email-verified
     * @return User
     */
    protected function createUser(array $attributes = [], bool $verified = true): User
    {
        if (!array_key_exists('email_verified_at', $attributes) && $verified) {
            $attributes['email_verified_at'] = now();
        }

        $user = User::factory()->create(array_merge([
            'password' => bcrypt($this->defaultPassword),
        ], $attributes));

        return $user->fresh();
    }

    /**
     * Create an API token for the given user.
     *
     * @param User $user The user to create token for
     * @param array<string> $abilities Token abilities
     * @param string $name Token name
     * @return string The plain text token
     */
    protected function createTokenForUser(
        User $user,
        array $abilities = ['*'],
        string $name = 'test-token'
    ): string {
        $token = $user->createToken($name, $abilities);
        return $token->plainTextToken;
    }

    /**
     * Assert that a valid auth token exists for the given user.
     *
     * @param User $user The user who should own the token
     * @param string|null $tokenName Optional token name to check
     * @param array<string>|null $abilities Optional abilities to verify
     * @return void
     */
    protected function assertValidAuthToken(
        User $user,
        ?string $tokenName = null,
        ?array $abilities = null
    ): void {
        $query = $user->tokens();

        if ($tokenName !== null) {
            $query->where('name', $tokenName);
        }

        $this->assertTrue(
            $query->exists(),
            'No token found for user ' . $user->getKey() .
            ($tokenName ? ' with name ' . $tokenName : '')
        );

        if ($abilities !== null) {
            $tokenQuery = $user->tokens();
            if ($tokenName !== null) {
                $tokenQuery->where('name', $tokenName);
            }

            $token = $tokenQuery->first();
            $this->assertNotNull($token, 'Token should not be null when checking abilities');

            if ($token !== null) {
                foreach ($abilities as $ability) {
                    $this->assertTrue(
                        $token->can($ability),
                        "Token should have ability: $ability"
                    );
                }
            }
        }
    }
}
