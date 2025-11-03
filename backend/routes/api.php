<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;

use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\NewPasswordController;

use App\Http\Controllers\Api\V1\TripController;
use App\Http\Controllers\Api\V1\TripUserController;
use App\Http\Controllers\Api\V1\PreferenceController;
use App\Http\Controllers\Api\V1\ItineraryController;
use App\Http\Controllers\Api\V1\TripPlaceController;
use App\Http\Controllers\Api\V1\PlaceController; // ✅ добавлено

Route::prefix('v1')->group(function () {

    // ---- Public (guest) routes ----
    Route::middleware('guest')->group(function () {
        Route::post('/register',        [RegisteredUserController::class, 'store']);
        Route::post('/login',           [AuthenticatedSessionController::class, 'store']);
        Route::post('/forgot-password', [PasswordResetLinkController::class, 'store']);
        Route::post('/reset-password',  [NewPasswordController::class, 'store']);
    });

    // ---- Authenticated routes ----
    Route::middleware('auth:sanctum')->scopeBindings()->group(function () {

        // ---- Trips CRUD ----
        Route::get   ('/trips',        [TripController::class, 'index']);
        Route::post  ('/trips',        [TripController::class, 'store']);
        Route::get   ('/trips/{trip}', [TripController::class, 'show']);
        Route::put   ('/trips/{trip}', [TripController::class, 'update']);
        Route::patch ('/trips/{trip}', [TripController::class, 'update']);
        Route::delete('/trips/{trip}', [TripController::class, 'destroy']);

        // ---- Invites ----
        Route::post  ('/trips/{trip}/members/invite', [TripUserController::class, 'invite']);
        Route::post  ('/trips/{trip}/accept',         [TripUserController::class, 'accept']);
        Route::post  ('/trips/{trip}/decline',        [TripUserController::class, 'decline']);

        // ---- User invites ----
        Route::get('/users/me/invites',        [TripUserController::class, 'myInvites']);
        Route::get('/users/me/invites/sent',   [TripUserController::class, 'sentInvites']);

        // ---- Members ----
        Route::get   ('/trips/{trip}/members',        [TripUserController::class, 'index']);
        Route::put   ('/trips/{trip}/members/{user}', [TripUserController::class, 'update']);
        Route::patch ('/trips/{trip}/members/{user}', [TripUserController::class, 'update']);
        Route::delete('/trips/{trip}/members/{user}', [TripUserController::class, 'destroy']);

        // ---- Preferences ----
        Route::get('/preferences', [PreferenceController::class, 'index']);
        Route::put('/users/me/preferences', [PreferenceController::class, 'update']);

        // ---- Itinerary ----
        Route::get('/trips/{trip}/itinerary', [ItineraryController::class, 'index']);

        // ---- Trip Places ----
        Route::get   ('/trips/{trip}/places',             [TripPlaceController::class, 'index']);
        Route::post  ('/trips/{trip}/places',             [TripPlaceController::class, 'store']);
        Route::delete('/trips/{trip}/places/{place}',     [TripPlaceController::class, 'destroy']);

        // ---- Places ----
        Route::get('/places/nearby', [PlaceController::class, 'nearby']); // ✅ добавлено

        // ---- User info ----
        Route::get('/user', fn (Request $r) => $r->user());

        // ---- Logout ----
        Route::post('/logout', [AuthenticatedSessionController::class, 'destroy']);
    });
});
