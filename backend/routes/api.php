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

Route::prefix('v1')->group(function () {

    Route::middleware('guest')->group(function () {
        Route::post('/register',        [RegisteredUserController::class, 'store']);
        Route::post('/login',           [AuthenticatedSessionController::class, 'store']);
        Route::post('/forgot-password', [PasswordResetLinkController::class, 'store']);
        Route::post('/reset-password',  [NewPasswordController::class, 'store']);
    });

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
        Route::get('/users/me/invites', [TripUserController::class, 'myInvites']);
        Route::get('/users/me/invites/sent', [TripUserController::class, 'sentInvites']);


        // ---- Members ----
        Route::get   ('/trips/{trip}/members',        [TripUserController::class, 'index']);
        Route::put   ('/trips/{trip}/members/{user}', [TripUserController::class, 'update']);
        Route::patch ('/trips/{trip}/members/{user}', [TripUserController::class, 'update']);
        Route::delete('/trips/{trip}/members/{user}', [TripUserController::class, 'destroy']);

        // ---- Preferences ----
        Route::get('/preferences', [PreferenceController::class, 'index']);
        Route::put('/users/me/preferences', [PreferenceController::class, 'update']);

        // ---- User info ----
        Route::get('/user', fn (Request $r) => $r->user());

        // ---- Logout ----
        Route::post('/logout', [AuthenticatedSessionController::class, 'destroy']);
    });
});
