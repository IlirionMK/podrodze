<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_password_link_can_be_requested(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $response = $this->postJson('/api/v1/forgot-password', [
            'email' => $user->email,
        ]);

        $this->assertTrue(in_array($response->getStatusCode(), [200, 204]), 'Unexpected status: '.$response->getStatusCode());
        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_password_can_be_reset_with_valid_token(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->postJson('/api/v1/forgot-password', [
            'email' => $user->email,
        ]);

        Notification::assertSentTo($user, ResetPassword::class, function (object $notification) use ($user) {
            $newPassword = 'new-password-123';

            $response = $this->postJson('/api/v1/reset-password', [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => $newPassword,
                'password_confirmation' => $newPassword,
            ]);

            $this->assertTrue(in_array($response->getStatusCode(), [200, 204]), 'Unexpected status: '.$response->getStatusCode());

            $login = $this->postJson('/api/v1/login', [
                'email' => $user->email,
                'password' => $newPassword,
            ]);

            $login->assertOk()->assertJsonStructure([
                'user'  => ['id', 'name', 'email'],
                'token',
            ]);

            return true;
        });
    }
}
