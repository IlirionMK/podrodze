<?php

use App\Http\Controllers\Api\V1\Admin\AdminActivityLogController;
use App\Http\Controllers\Api\V1\Admin\AdminUserController;
use App\Http\Controllers\Api\V1\FacebookDataDeletionController;
use App\Http\Controllers\Api\V1\ItineraryController;
use App\Http\Controllers\Api\V1\MeController;
use App\Http\Controllers\Api\V1\PlaceController;
use App\Http\Controllers\Api\V1\PreferenceController;
use App\Http\Controllers\Api\V1\TripController;
use App\Http\Controllers\Api\V1\TripPlaceController;
use App\Http\Controllers\Api\V1\TripPlaceSuggestionsController;
use App\Http\Controllers\Api\V1\TripUserController;
use App\Http\Controllers\Auth\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\Auth\GoogleAuthController;
use App\Http\Controllers\Auth\Auth\NewPasswordController;
use App\Http\Controllers\Auth\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\FacebookAuthController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\VerifyEmailController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

    Route::post('/register', [RegisteredUserController::class, 'store']);
    Route::post('/login', [AuthenticatedSessionController::class, 'store']);
    Route::post('/forgot-password', [PasswordResetLinkController::class, 'store']);
    Route::post('/reset-password', [NewPasswordController::class, 'store']);

    Route::get('/email/verify/{id}/{hash}', VerifyEmailController::class)
        ->middleware(['signed:relative', 'throttle:6,1'])
        ->name('verification.verify');

    Route::get('/auth/google/url', [GoogleAuthController::class, 'getAuthUrl']);
    Route::post('/auth/google/callback', [GoogleAuthController::class, 'handleCallback']);
    Route::get('/auth/facebook/url', [FacebookAuthController::class, 'getAuthUrl']);
    Route::post('/auth/facebook/callback', [FacebookAuthController::class, 'handleCallback']);

    Route::post('/facebook/data-deletion', [FacebookDataDeletionController::class, 'handle']);
    Route::get('/facebook/data-deletion/status/{code}', [FacebookDataDeletionController::class, 'status']);

    Route::get('/google/maps-key', function () {
        return response()->json([
            'key' => env('GOOGLE_MAPS_KEY'),
        ]);
    });

    Route::middleware(['auth:sanctum', 'not_banned'])->scopeBindings()->group(function () {

        Route::get('/users/me', [MeController::class, 'show']);
        Route::patch('/users/me', [MeController::class, 'update']);
        Route::put('/users/me/password', [MeController::class, 'updatePassword']);
        Route::delete('/users/me', [MeController::class, 'destroy']);

        Route::get('/trips', [TripController::class, 'index']);
        Route::post('/trips', [TripController::class, 'store']);
        Route::get('/trips/{trip}', [TripController::class, 'show']);
        Route::put('/trips/{trip}', [TripController::class, 'update']);
        Route::patch('/trips/{trip}', [TripController::class, 'update']);
        Route::delete('/trips/{trip}', [TripController::class, 'destroy']);
        Route::patch('/trips/{trip}/start-location', [TripController::class, 'updateStartLocation']);

        Route::post('/trips/{trip}/members/invite', [TripUserController::class, 'invite']);
        Route::post('/trips/{trip}/accept', [TripUserController::class, 'accept']);
        Route::post('/trips/{trip}/decline', [TripUserController::class, 'decline']);

        Route::get('/users/me/invites', [TripUserController::class, 'myInvites']);
        Route::get('/users/me/invites/sent', [TripUserController::class, 'sentInvites']);

        Route::get('/trips/{trip}/members', [TripUserController::class, 'index']);
        Route::put('/trips/{trip}/members/{user}', [TripUserController::class, 'update'])->withoutScopedBindings();
        Route::patch('/trips/{trip}/members/{user}', [TripUserController::class, 'update'])->withoutScopedBindings();
        Route::delete('/trips/{trip}/members/{user}', [TripUserController::class, 'destroy'])->withoutScopedBindings();

        Route::get('/preferences', [PreferenceController::class, 'index']);
        Route::put('/users/me/preferences', [PreferenceController::class, 'update']);

        Route::get('/trips/{trip}/preferences/aggregate', [ItineraryController::class, 'aggregatePreferences']);
        Route::get('/trips/{trip}/itinerary/generate', [ItineraryController::class, 'generate']);
        Route::post('/trips/{trip}/itinerary/generate-full', [ItineraryController::class, 'generateFullRoute']);
        Route::get('/trips/{trip}/itinerary', [ItineraryController::class, 'show']);
        Route::patch('/trips/{trip}/itinerary', [ItineraryController::class, 'update']);
        Route::get('/trips/{trip}/places/suggestions', TripPlaceSuggestionsController::class)
            ->middleware('throttle:30,1');

        Route::get('/trips/{trip}/places/nearby', [TripPlaceController::class, 'nearbyGoogle'])
            ->middleware('throttle:30,1');

        Route::get('/trips/{trip}/places', [TripPlaceController::class, 'index']);
        Route::post('/trips/{trip}/places', [TripPlaceController::class, 'store']);
        Route::patch('/trips/{trip}/places/{place}', [TripPlaceController::class, 'update']);
        Route::delete('/trips/{trip}/places/{place}', [TripPlaceController::class, 'destroy']);

        Route::get('/trips/{trip}/places/votes', [TripPlaceController::class, 'votes']);
        Route::post('/trips/{trip}/places/{place}/vote', [TripPlaceController::class, 'vote']);

        Route::get('/places/nearby', [PlaceController::class, 'nearby']);

        Route::get('/places/autocomplete', [PlaceController::class, 'autocomplete'])
            ->middleware('throttle:60,1');

        Route::get('/places/google/{googlePlaceId}', [PlaceController::class, 'googleDetails'])
            ->middleware('throttle:60,1');

        Route::get('/user', fn (Request $request) => $request->user());

        Route::post('/logout', [AuthenticatedSessionController::class, 'destroy']);

        Route::prefix('admin')
            ->middleware('admin')
            ->group(function () {
                Route::get('/health', fn () => response()->json(['ok' => true]));
                Route::get('/users', [AdminUserController::class, 'index']);
                Route::patch('/users/{user}/role', [AdminUserController::class, 'setRole']);
                Route::patch('/users/{user}/ban', [AdminUserController::class, 'setBanned']);
                Route::get('/logs/activity', [AdminActivityLogController::class, 'index']);
            });
    });
});
