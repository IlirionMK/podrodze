<?php

namespace App\Providers;

use App\Models\Trip;
use App\Policies\TripPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\URL;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Trip::class => TripPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        // Make verification link SPA-friendly:
        // email -> frontend route -> frontend calls signed API URL
        VerifyEmail::createUrlUsing(function ($notifiable) {
            $apiSignedUrl = URL::temporarySignedRoute(
                'verification.verify',
                now()->addMinutes(config('auth.verification.expire', 60)),
                [
                    'id' => $notifiable->getKey(),
                    'hash' => sha1($notifiable->getEmailForVerification()),
                ]
            );

            $frontend = config('app.frontend_url', 'http://localhost:5173');

            return $frontend . '/auth/verify-email?url=' . urlencode($apiSignedUrl);
        });
    }
}
