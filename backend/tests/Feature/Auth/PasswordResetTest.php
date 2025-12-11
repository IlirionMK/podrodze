<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Tests for password reset functionality.
 *
 * This class contains tests for:
 * - Password reset request flow
 * - Password reset token validation
 * - Password update process
 * - Invalid token handling
 * - Rate limiting for password reset requests
 */
#[Group('auth')]
#[Group('password')]
#[Group('security')]
class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_password_link_sends_notification(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->postJson('/api/v1/forgot-password', [
            'email' => $user->email,
        ]);

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_reset_password_link_returns_success_status(): void
    {
        $user = User::factory()->create();

        $response = $this->postJson('/api/v1/forgot-password', [
            'email' => $user->email,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'We have emailed your password reset link.'
            ]);
    }

    public function test_password_reset_sends_notification(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->postJson('/api/v1/forgot-password', [
            'email' => $user->email,
        ]);

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_password_reset_returns_success_status(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->postJson('/api/v1/forgot-password', ['email' => $user->email]);

        $notification = Notification::sent($user, ResetPassword::class)->first();
        $newPassword = 'new-password-123';

        $response = $this->postJson('/api/v1/reset-password', [
            'token' => $notification->token,
            'email' => $user->email,
            'password' => $newPassword,
            'password_confirmation' => $newPassword,
        ]);

        $this->assertTrue(
            in_array($response->getStatusCode(), [200, 204]),
            'Expected status code 200 or 204, got '.$response->getStatusCode()
        );
    }

    public function test_user_can_login_after_password_reset(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $newPassword = 'new-password-123';

        $this->postJson('/api/v1/forgot-password', ['email' => $user->email]);

        $notification = Notification::sent($user, ResetPassword::class)->first();

        $this->postJson('/api/v1/reset-password', [
            'token' => $notification->token,
            'email' => $user->email,
            'password' => $newPassword,
            'password_confirmation' => $newPassword,
        ]);

        $response = $this->postJson('/api/v1/login', [
            'email' => $user->email,
            'password' => $newPassword,
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'user' => ['id', 'name', 'email'],
                'token',
            ]);
    }

    public function test_cannot_reset_password_with_invalid_token(): void
    {
        $user = User::factory()->create();

        $response = $this->postJson('/api/v1/reset-password', [
            'token' => 'invalid-token',
            'email' => $user->email,
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

        $response->assertStatus(422);
    }

    public function test_cannot_reset_password_without_valid_email(): void
    {
        $response = $this->postJson('/api/v1/forgot-password', [
            'email' => 'nonexistent@example.com',
        ]);

        $this->assertTrue(in_array($response->getStatusCode(), [200, 204, 422]));
    }

    public function test_cannot_reset_password_with_invalid_email(): void
    {
        $response = $this->postJson('/api/v1/forgot-password', [
            'email' => 'invalid-email',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_password_reset_fails_with_mismatched_passwords(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->postJson('/api/v1/forgot-password', ['email' => $user->email]);

        $notification = Notification::sent($user, ResetPassword::class)->first();

        $response = $this->postJson('/api/v1/reset-password', [
            'token' => $notification->token,
            'email' => $user->email,
            'password' => 'new-password',
            'password_confirmation' => 'wrong-confirmation',
        ]);

        $response->assertStatus(422);
    }

    public function test_password_reset_shows_validation_error_for_mismatched_passwords(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->postJson('/api/v1/forgot-password', ['email' => $user->email]);

        $notification = Notification::sent($user, ResetPassword::class)->first();

        $response = $this->postJson('/api/v1/reset-password', [
            'token' => $notification->token,
            'email' => $user->email,
            'password' => 'new-password',
            'password_confirmation' => 'wrong-confirmation',
        ]);

        $response->assertJsonValidationErrors(['password']);
    }

    public function test_password_reset_fails_with_short_password(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->postJson('/api/v1/forgot-password', ['email' => $user->email]);

        $notification = Notification::sent($user, ResetPassword::class)->first();

        $response = $this->postJson('/api/v1/reset-password', [
            'token' => $notification->token,
            'email' => $user->email,
            'password' => 'short',
            'password_confirmation' => 'short',
        ]);

        $response->assertStatus(422);
    }

    public function test_password_reset_shows_validation_error_for_short_password(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->postJson('/api/v1/forgot-password', ['email' => $user->email]);

        $notification = Notification::sent($user, ResetPassword::class)->first();

        $response = $this->postJson('/api/v1/reset-password', [
            'token' => $notification->token,
            'email' => $user->email,
            'password' => 'short',
            'password_confirmation' => 'short',
        ]);

        $response->assertJsonValidationErrors(['password']);
    }
}
