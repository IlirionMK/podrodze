<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Auth\Notifications\ResetPassword;
use App\Interfaces\{ItineraryServiceInterface,
    PreferenceAggregatorServiceInterface,
    PreferenceServiceInterface,
    TripInterface
    };
use App\Services\{ItineraryService, PreferenceAggregatorService, PreferenceService, TripService};

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Bind only interfaces that are actually used via DI
        $this->app->bind(ItineraryServiceInterface::class, ItineraryService::class);
        $this->app->bind(PreferenceServiceInterface::class, PreferenceService::class);
        $this->app->bind(TripInterface::class, TripService::class);
        $this->app->bind(PreferenceAggregatorServiceInterface::class, PreferenceAggregatorService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        ResetPassword::createUrlUsing(function (object $notifiable, string $token) {
            return config('app.frontend_url') .
                "/password-reset/$token?email={$notifiable->getEmailForPasswordReset()}";
        });
    }
}
