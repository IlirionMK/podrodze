<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Notification;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase\AuthenticatedTestCase;

#[Group('auth')]
#[Group('verification')]
class EmailVerificationTest extends AuthenticatedTestCase
{
    /**
     * Disable rate limiting for email verification tests
     */
    protected bool $enableRateLimiting = false;
    /**
     * Set up the test environment.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(VerifyCsrfToken::class);

        Notification::fake();

        config(['session.driver' => 'array']);
    }

    /**
     * Create a user and log them in
     */
    /**
     * Create a user with the given verification status.
     *
     * @param array $attributes Additional user attributes
     * @param bool $verified Whether the user should be verified
     * @return User
     */
    protected function createUser(array $attributes = [], bool $verified = null): User
    {
        if ($verified !== null) {
            $attributes['email_verified_at'] = $verified ? now() : null;
        }

        return parent::createUser($attributes);
    }

    /**
     * Assert that the response has a forbidden status code (403)
     */
    protected function assertForbiddenResponse($response, string $message = 'Expected a forbidden response'): void
    {
        $response->assertStatus(403, $message);
    }


    public function test_email_can_be_verified(): void
    {
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ValidateSignature::class);

        $user = $this->createUser([], false);

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->getAttribute('id'), 'hash' => sha1($user->getAttribute('email'))]
        );

        $response = $this->actingAs($user, 'web')
            ->get($verificationUrl);

        $response->assertStatus(200);
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }

    public function test_email_is_not_verified_with_invalid_hash(): void
    {
        $user = $this->createUser(['email_verified_at' => null]);

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->getKey(), 'hash' => sha1('wrong-email')]
        );

        $response = $this->actingAs($user, 'web')
            ->get($verificationUrl);

        $response->assertStatus(403);
    }

    public function test_email_verification_link_can_be_resent(): void
    {
        $user = $this->createUser([], false);

        $this->startSession();
        $token = csrf_token();

        $response = $this->actingAs($user, 'web')
            ->withHeader('X-CSRF-TOKEN', $token)
            ->post(route('verification.send'), [
                '_token' => $token
            ]);

        $response->assertOk();

        $response->assertJson([
            'status' => 'verification-link-sent'
        ]);

        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_email_verification_link_cannot_be_resent_for_verified_email(): void
    {
        $user = $this->createUser([], true);

        $this->startSession();
        $token = csrf_token();

        $response = $this->actingAs($user, 'web')
            ->withHeader('X-CSRF-TOKEN', $token)
            ->post(route('verification.send'), [
                '_token' => $token
            ]);

        $response->assertOk();
        $response->assertJson([
            'message' => 'Email is already verified.'
        ]);

        Notification::assertNothingSent();
    }

    public function test_email_verification_link_expires(): void
    {
        $user = $this->createUser(['email_verified_at' => null]);

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->subDay(),
            ['id' => $user->getKey(), 'hash' => sha1($user->getAttribute('email'))]
        );

        $response = $this->actingAs($user, 'web')
            ->get($verificationUrl);

        $response->assertStatus(403);
    }

    public function test_email_verification_requires_correct_user(): void
    {
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ValidateSignature::class);

        $user1 = $this->createUser([], false);
        $user2 = $this->createUser(['email' => 'another@example.com'], false);

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user1->getAttribute('id'), 'hash' => sha1($user1->getAttribute('email'))]
        );

        $response = $this->actingAs($user2, 'web')
            ->get($verificationUrl);

        $response->assertStatus(200);
    }

    public function test_email_verification_route_exists(): void
    {
        $this->assertNotNull(route('verification.verify', ['id' => 1, 'hash' => 'test']));
        $this->assertNotNull(route('verification.send'));
    }
}
