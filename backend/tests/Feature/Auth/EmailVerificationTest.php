<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Auth\Notifications\VerifyEmail;
use Tests\TestCase;

/**
 * Tests for email verification functionality.
 *
 * This class contains tests for:
 * - Email verification link generation and validation
 * - Resending verification emails
 * - Handling of already verified emails
 * - Protection of routes requiring email verification
 * - Verification link expiration and security
 */
#[Group('auth')]
#[Group('verification')]
class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        //Wyłączenie weryfikacji tokenu CSRF - dla testów jednostkowych
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);

        config(['app.frontend_url' => 'http://localhost:5173/']);

        if (!env('APP_KEY')) {
            config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);
        }
    }

    public function test_email_can_be_verified(): void
    {
        $user = User::factory()->unverified()->create();
        Event::fake();

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email)]
        );

        $response = $this->actingAs($user, 'web')
            ->get($verificationUrl);

        Event::assertDispatched(Verified::class);
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }

    public function test_email_is_not_verified_with_invalid_hash(): void
    {
        $user = User::factory()->unverified()->create();

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1('wrong-email')]
        );

        $response = $this->actingAs($user, 'web')
            ->withSession(['_token' => 'test'])
            ->get($verificationUrl);

        $response->assertStatus(403);
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_email_verification_link_can_be_resent()
    {
        Notification::fake();

        $user = User::factory()->unverified()->create([
            'email' => 'test@example.com'
        ]);

        // Wywołanie akcji z tokenem CSRF
        $response = $this->actingAs($user)
            ->withSession(['_token' => 'test-token'])
            ->post(route('verification.send'), [
                '_token' => 'test-token'
            ]);

        // Sprawdzenie odpowiedzi
        $response->assertStatus(200)
            ->assertJson(['status' => 'verification-link-sent']);

        // Sprawdzenie czy powiadomienie zostało wysłane

        Notification::assertSentTo(
            $user,
            VerifyEmail::class
        );
    }

    public function test_email_verification_link_cannot_be_resent_for_verified_email()
    {
        $user = User::factory()->create([
            'email_verified_at' => now()
        ]);

        // Dodajemy debugowanie sesji
        $this->withSession(['_token' => 'test-token']);

        $response = $this->actingAs($user, 'web')
            ->withHeader('X-CSRF-TOKEN', 'test-token')
            ->post(route('verification.send'), [
                '_token' => 'test-token'
            ]);

        // Tymczasowo wyłączamy asercję redirecta, aby zobaczyć pełną odpowiedź
        if ($response->status() !== 302) {
            dd([
                'status' => $response->status(),
                'content' => $response->content(),
                'session' => session()->all(),
                'headers' => $response->headers
            ]);
        }

        $response->assertStatus(302)
            ->assertRedirect('/dashboard');
    }

    public function test_email_verification_link_expires(): void
    {
        $user = User::factory()->unverified()->create();

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->subDay(),
            ['id' => $user->id, 'hash' => sha1($user->email)]
        );

        $response = $this->actingAs($user, 'web')
            ->withSession(['_token' => 'test'])
            ->get($verificationUrl);

        $response->assertStatus(403);
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_email_verification_requires_authentication(): void
    {
        $user = User::factory()->unverified()->create();

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email)]
        );

        $response = $this->get($verificationUrl);

        $response->assertStatus(302)
            ->assertRedirect(route('login'));
    }

    public function test_email_verification_requires_correct_user(): void
    {
        $user1 = User::factory()->unverified()->create();
        $user2 = User::factory()->create();

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user1->id, 'hash' => sha1($user1->email)]
        );

        $response = $this->actingAs($user2, 'web')
            ->withSession(['_token' => 'test'])
            ->get($verificationUrl);

        $response->assertStatus(403);
        $this->assertFalse($user1->fresh()->hasVerifiedEmail());
    }

    public function test_email_verification_route_exists(): void
    {
        $this->assertNotNull(route('verification.verify', ['id' => 1, 'hash' => 'test']));
        $this->assertNotNull(route('verification.send'));
    }
}
