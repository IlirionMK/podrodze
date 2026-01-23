<?php

namespace Http\Controllers\Auth;

use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class EmailVerificationNotificationControllerTest extends TestCase
{
    private EmailVerificationNotificationController $controller;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->controller = new EmailVerificationNotificationController();
        $this->user = User::factory()->create([
            'email_verified_at' => null,
        ]);
    }

    protected function tearDown(): void
    {
        $this->user->delete();
        parent::tearDown();
    }

    public function test_store_sends_verification_email()
    {
        Notification::fake();

        $request = Request::create('/email/verification-notification', 'POST');
        $request->setUserResolver(fn () => $this->user);

        $response = $this->controller->store($request);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals(
            ['status' => 'verification-link-sent', 'message' => 'Verification link sent.'],
            $response->getData(true)
        );

        Notification::assertSentTo($this->user, \Illuminate\Auth\Notifications\VerifyEmail::class);
    }

    public function test_store_already_verified()
    {
        $verifiedUser = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $request = Request::create('/email/verification-notification', 'POST');
        $request->setUserResolver(fn () => $verifiedUser);

        $response = $this->controller->store($request);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals(
            [
                'status' => 'already-verified',
                'message' => 'Email is already verified.'
            ],
            $response->getData(true)
        );

        $verifiedUser->delete();
    }
}
