<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;

// ---- Auth controllers ----
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\GoogleAuthController;

// ---- API V1 controllers ----
use App\Http\Controllers\Api\V1\TripController;
use App\Http\Controllers\Api\V1\TripUserController;
use App\Http\Controllers\Api\V1\PreferenceController;
use App\Http\Controllers\Api\V1\ItineraryController;
use App\Http\Controllers\Api\V1\TripPlaceController;
use App\Http\Controllers\Api\V1\PlaceController;

Route::prefix('v1')->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Public routes (no auth)
    |--------------------------------------------------------------------------
    */
    Route::post('/register',        [RegisteredUserController::class, 'store']);
    Route::post('/login',           [AuthenticatedSessionController::class, 'store']);
    Route::post('/forgot-password', [PasswordResetLinkController::class, 'store']);
    Route::post('/reset-password',  [NewPasswordController::class, 'store']);

    // Google OAuth
    Route::get('/auth/google/url',       [GoogleAuthController::class, 'getAuthUrl']);
    Route::post('/auth/google/callback', [GoogleAuthController::class, 'handleCallback']);

    /*
    |--------------------------------------------------------------------------
    | Public utility routes
    |--------------------------------------------------------------------------
    */
    Route::get('/google/maps-key', function () {
        return response()->json([
            'key' => env('GOOGLE_MAPS_KEY'),
        ]);
    });

    /*
    |--------------------------------------------------------------------------
    | Authenticated routes (Sanctum)
    |--------------------------------------------------------------------------
    */
    Route::middleware('auth:sanctum')->scopeBindings()->group(function () {

        // Trips CRUD
        Route::get   ('/trips',        [TripController::class, 'index']);
        Route::post  ('/trips',        [TripController::class, 'store']);
        Route::get   ('/trips/{trip}', [TripController::class, 'show']);
        Route::put   ('/trips/{trip}', [TripController::class, 'update']);
        Route::patch ('/trips/{trip}', [TripController::class, 'update']);
        Route::delete('/trips/{trip}', [TripController::class, 'destroy']);
        Route::patch('/trips/{trip}/start-location', [TripController::class, 'updateStartLocation']);

        // Trip invites & membership
        Route::post('/trips/{trip}/members/invite', [TripUserController::class, 'invite']);
        Route::post('/trips/{trip}/accept',         [TripUserController::class, 'accept']);
        Route::post('/trips/{trip}/decline',        [TripUserController::class, 'decline']);

        Route::get('/users/me/invites',      [TripUserController::class, 'myInvites']);
        Route::get('/users/me/invites/sent', [TripUserController::class, 'sentInvites']);

        Route::get   ('/trips/{trip}/members',        [TripUserController::class, 'index']);
        Route::put   ('/trips/{trip}/members/{user}', [TripUserController::class, 'update'])->withoutScopedBindings();
        Route::patch ('/trips/{trip}/members/{user}', [TripUserController::class, 'update'])->withoutScopedBindings();
        Route::delete('/trips/{trip}/members/{user}', [TripUserController::class, 'destroy'])->withoutScopedBindings();

        // Preferences
        Route::get('/preferences',          [PreferenceController::class, 'index']);
        Route::put('/users/me/preferences', [PreferenceController::class, 'update']);

        // Itinerary
        Route::get ('/trips/{trip}/preferences/aggregate',   [ItineraryController::class, 'aggregatePreferences']);
        Route::get ('/trips/{trip}/itinerary/generate',      [ItineraryController::class, 'generate']);
        Route::post('/trips/{trip}/itinerary/generate-full', [ItineraryController::class, 'generateFullRoute']);

        // Trip Places
        Route::get   ('/trips/{trip}/places',              [TripPlaceController::class, 'index']);
        Route::post  ('/trips/{trip}/places',              [TripPlaceController::class, 'store']);
        Route::patch ('/trips/{trip}/places/{place}',      [TripPlaceController::class, 'update']);
        Route::delete('/trips/{trip}/places/{place}',      [TripPlaceController::class, 'destroy']);
        Route::post  ('/trips/{trip}/places/{place}/vote', [TripPlaceController::class, 'vote']);

        // Places
        Route::get('/places/nearby', [PlaceController::class, 'nearby']);

        // User info
        Route::get('/user', fn (Request $r) => $r->user());

        // Logout
        Route::post('/logout', [AuthenticatedSessionController::class, 'destroy']);
    });
});
