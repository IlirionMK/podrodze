<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Exception;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase\ApiTestCase;
use Illuminate\Testing\TestResponse;

#[Group('auth')]
#[Group('password')]
#[Group('security')]
class PasswordResetTest extends ApiTestCase
{
    /**
     * @var bool Enable rate limiting for tests
     */
    protected bool $enableRateLimiting = true;

    /**
     * @var string The new password for testing
     */
    private const NEW_PASSWORD = 'new-password-123';

    /**
     * @var string An invalid email for testing
     */
    private const INVALID_EMAIL = 'nonexistent@example.com';

    /**
     * Assert that the response has a successful status code (2xx).
     *
     * @param TestResponse $response The response to check
     */
    protected function assertSuccessResponse(TestResponse $response): void
    {
        parent::assertSuccessResponse($response);
    }

    /**
     * Assert that the response has an unprocessable entity status code (422).
     *
     * @param TestResponse $response The response to check
     */
    protected function assertUnprocessableResponse(TestResponse $response): void
    {
        parent::assertUnprocessableResponse($response);
    }


    public function test_reset_password_link_sends_notification(): void
    {
        Notification::fake();
        $user = $this->createUser();

        $response = $this->postJson('/api/v1/forgot-password', ['email' => $user->getAttribute('email')]);
        $response->assertStatus(200);

        try {
            Notification::assertSentTo($user, ResetPassword::class);
        } catch (Exception $e) {
            $this->fail('Failed to send password reset notification: ' . $e->getMessage());
        }
    }

    public function test_reset_password_link_returns_success_status(): void
    {
        $user = $this->createUser();

        $response = $this->postJson('/api/v1/forgot-password', [
            'email' => $user->getAttribute('email'),
        ]);

        $response->assertOk()
            ->assertJson(['status' => 'We have emailed your password reset link.']);
    }

    public function test_can_reset_password_with_valid_token(): void
    {
        $user = $this->createUser();
        $token = $this->getPasswordResetToken($user);

        $response = $this->postJson('/api/v1/reset-password', [
            'token' => $token,
            'email' => $user->getAttribute('email'),
            'password' => self::NEW_PASSWORD,
            'password_confirmation' => self::NEW_PASSWORD,
        ]);

        $this->assertSuccessResponse($response);
    }

    public function test_user_can_login_after_password_reset(): void
    {
        $user = $this->createUser();
        $token = $this->getPasswordResetToken($user);

        $this->postJson('/api/v1/reset-password', [
            'token' => $token,
            'email' => $user->getAttribute('email'),
            'password' => self::NEW_PASSWORD,
            'password_confirmation' => self::NEW_PASSWORD,
        ]);

        $response = $this->postJson('/api/v1/login', [
            'email' => $user->getAttribute('email'),
            'password' => self::NEW_PASSWORD,
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'user' => ['id', 'name', 'email'],
                'token',
            ]);
    }

    public function test_cannot_reset_password_with_invalid_token(): void
    {
        $user = $this->createUser();

        $response = $this->postJson('/api/v1/reset-password', [
            'token' => 'invalid-token',
            'email' => $user->getAttribute('email'),
            'password' => self::NEW_PASSWORD,
            'password_confirmation' => self::NEW_PASSWORD,
        ]);

        $this->assertUnprocessableResponse($response);
    }

    public function test_cannot_reset_password_with_nonexistent_email(): void
    {
        $response = $this->postJson('/api/v1/forgot-password', [
            'email' => self::INVALID_EMAIL,
        ]);

        $this->assertSuccessResponse($response);
    }

    public function test_cannot_reset_password_with_invalid_email_format(): void
    {
        $response = $this->postJson('/api/v1/forgot-password', [
            'email' => 'invalid-email',
        ]);

        $this->assertUnprocessableResponse($response);
        $response->assertJsonValidationErrors(['email']);
    }

    public function test_cannot_reset_password_with_mismatched_passwords(): void
    {
        $user = $this->createUser();
        $token = $this->getPasswordResetToken($user);

        $response = $this->postJson('/api/v1/reset-password', [
            'token' => $token,
            'email' => $user->getAttribute('email'),
            'password' => self::NEW_PASSWORD,
            'password_confirmation' => 'wrong-confirmation',
        ]);

        // The API returns 422 with validation errors
        $this->assertUnprocessableResponse($response);

        // Check for the specific validation error
        $response->assertJsonValidationErrors(['password']);
    }

    public function test_cannot_reset_password_with_short_password(): void
    {
        $user = $this->createUser();
        $token = $this->getPasswordResetToken($user);

        $response = $this->postJson('/api/v1/reset-password', [
            'token' => $token,
            'email' => $user->getAttribute('email'),
            'password' => 'short',
            'password_confirmation' => 'short',
        ]);

        // The API returns 422 with validation errors
        $this->assertUnprocessableResponse($response);

        // Check for the specific validation error
        $response->assertJsonValidationErrors(['password']);
    }

    /**
     * Get password reset token for testing
     *
     * @param User $user The user to get the reset token for
     * @return string The password reset token
     */
    private function getPasswordResetToken(User $user): string
    {
        Notification::fake();
        $this->postJson('/api/v1/forgot-password', ['email' => $user->getAttribute('email')]);
        return Notification::sent($user, ResetPassword::class)->first()->token;
    }
}
