<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Auth\Notifications\ResetPassword;

use App\Interfaces\{
    ItineraryServiceInterface,
    PlaceInterface,
    PreferenceAggregatorServiceInterface,
    PreferenceServiceInterface,
    TripInterface
};
use App\Services\{
    ItineraryService,
    PlaceService,
    PreferenceAggregatorService,
    PreferenceService,
    TripService
};

// NEW: AI suggestions
use App\Interfaces\Ai\AiPlaceAdvisorInterface;
use App\Interfaces\Ai\AiPlaceReasonerInterface;
use App\Interfaces\Ai\PlacesCandidateProviderInterface;

use App\Services\Ai\AiPlaceAdvisorService;
use App\Services\Ai\CategoryNormalizer;
use App\Services\Ai\Candidates\DatabasePlacesCandidateProvider;
use App\Services\Ai\Reasoners\HeuristicPlaceReasoner;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Existing bindings
        $this->app->bind(ItineraryServiceInterface::class, ItineraryService::class);
        $this->app->bind(PreferenceServiceInterface::class, PreferenceService::class);
        $this->app->bind(TripInterface::class, TripService::class);
        $this->app->bind(PlaceInterface::class, PlaceService::class);
        $this->app->bind(PreferenceAggregatorServiceInterface::class, PreferenceAggregatorService::class);

        // NEW: AI suggestions bindings
        $this->app->singleton(CategoryNormalizer::class);

        $this->app->bind(AiPlaceAdvisorInterface::class, AiPlaceAdvisorService::class);
        $this->app->bind(PlacesCandidateProviderInterface::class, DatabasePlacesCandidateProvider::class);
        $this->app->bind(AiPlaceReasonerInterface::class, HeuristicPlaceReasoner::class);
    }

    public function boot(): void
    {
        ResetPassword::createUrlUsing(function (object $notifiable, string $token) {
            return config('app.frontend_url') .
                "/password-reset/$token?email={$notifiable->getEmailForPasswordReset()}";
        });
    }
}
