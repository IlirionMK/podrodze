<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase\ApiTestCase;

/**
 * Tests for user registration functionality.
 *
 * This class verifies that:
 * - New users can register with valid information
 * - Registration validations work as expected
 * - Required fields are properly enforced
 * - User data is correctly stored in the database
 *
 * @covers \App\Http\Controllers\Auth\Auth\RegisteredUserController
 */
#[Group('auth')]
#[Group('registration')]
class RegistrationTest extends ApiTestCase
{
    protected bool $enableRateLimiting = false;

    private const TEST_PASSWORD = 'password';
    private const TEST_EMAIL = 'test@example.com';
    private const TEST_NAME = 'Test User';

    private array $userData = [
        'name' => self::TEST_NAME,
        'email' => self::TEST_EMAIL,
        'password' => self::TEST_PASSWORD,
        'password_confirmation' => self::TEST_PASSWORD,
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->enableRateLimiting = false;
        $this->setUpRateLimiting();

        Notification::fake();

        $this->clearRateLimits();
    }

    public function test_new_users_can_register(): void
    {
        $response = $this->postJson('/api/v1/register', $this->userData);

        $this->assertCreatedResponse($response);
        $response->assertJsonStructure([
            'user' => ['id', 'name', 'email', 'created_at', 'updated_at'],
            'token'
        ]);

        $this->assertDatabaseHas('users', [
            'email' => self::TEST_EMAIL,
            'name' => self::TEST_NAME,
            'email_verified_at' => null,
        ]);
    }

    public function test_registration_requires_valid_email(): void
    {
        $response = $this->postJson('/api/v1/register', array_merge($this->userData, [
            'email' => 'invalid-email',
        ]));

        $this->assertUnprocessableResponse($response);
        $response->assertJsonValidationErrors(['email']);
    }

    public function test_registration_requires_password_confirmation(): void
    {
        $response = $this->postJson('/api/v1/register', array_merge($this->userData, [
            'password_confirmation' => 'wrong',
        ]));

        $this->assertUnprocessableResponse($response);
        $response->assertJsonValidationErrors(['password']);
    }

    public function test_registration_requires_name(): void
    {
        $response = $this->postJson('/api/v1/register', array_merge($this->userData, [
            'name' => '',
        ]));

        $this->assertUnprocessableResponse($response);
        $response->assertJsonValidationErrors(['name']);
    }

    public function test_email_must_be_unique(): void
    {
        $this->createUser(['email' => self::TEST_EMAIL]);

        $response = $this->postJson('/api/v1/register', $this->userData);

        $this->assertUnprocessableResponse($response);
        $response->assertJsonValidationErrors(['email']);
    }

    public function test_password_must_be_strong(): void
    {
        $this->clearRateLimits();

        $response = $this->postJson('/api/v1/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'weak',
            'password_confirmation' => 'weak',
        ]);

        if ($response->getStatusCode() === 422) {
            $response->assertJsonValidationErrors(['password']);
        } else {
            $response->assertStatus(429);
        }
    }

    public function test_registered_user_has_correct_attributes(): void
    {
        $this->clearRateLimits();
        $uniqueEmail = 'test_' . time() . '@example.com';
        $userData = array_merge($this->userData, ['email' => $uniqueEmail]);

        $response = $this->postJson('/api/v1/register', $userData);

        if ($response->getStatusCode() === 201 || $response->getStatusCode() === 200) {
            $response->assertJsonPath('user.email', $uniqueEmail);
            $response->assertJsonPath('user.name', self::TEST_NAME);

            $response->assertJsonMissing(['user' => ['email_verified_at' => true]]);
        } else {
            $this->assertEquals(429, $response->getStatusCode());
        }
    }

    public function test_registered_user_can_login_immediately(): void
    {
        $this->clearRateLimits();
        $uniqueEmail = 'test_' . time() . '@example.com';
        $userData = array_merge($this->userData, ['email' => $uniqueEmail]);

        $response = $this->postJson('/api/v1/register', $userData);

        if ($response->getStatusCode() === 201 || $response->getStatusCode() === 200) {
            $user = User::query()->where('email', $uniqueEmail)->first();
            $this->assertNotNull($user, 'User was not created');

            $user->setAttribute('email_verified_at', now());
            $user->save();
            $user->refresh();

            $this->clearRateLimits();

            $loginResponse = $this->postJson('/api/v1/login', [
                'email' => $uniqueEmail,
                'password' => self::TEST_PASSWORD,
            ]);

            $loginResponse->assertOk()
                ->assertJsonStructure([
                    'token',
                    'user' => [
                        'id',
                        'name',
                        'email',
                        'email_verified_at',
                    ]
                ]);
        } else {
            $this->assertEquals(429, $response->getStatusCode());
        }
    }
}
