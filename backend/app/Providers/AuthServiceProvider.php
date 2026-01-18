<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\URL;

class AuthServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        VerifyEmail::createUrlUsing(function ($notifiable) {
            $signedPath = URL::temporarySignedRoute(
                'verification.verify',
                now()->addMinutes(config('auth.verification.expire', 60)),
                [
                    'id' => $notifiable->getKey(),
                    'hash' => sha1($notifiable->getEmailForVerification()),
                ],
                false
            );

            $apiSignedUrl = rtrim(config('app.url'), '/') . $signedPath;

            $frontend = rtrim(config('app.frontend_url', 'http://localhost:5173'), '/');

            return $frontend . '/auth/verify-email?url=' . urlencode($apiSignedUrl);
        });
    }
}
