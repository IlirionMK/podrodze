<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;

use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\NewPasswordController;

use App\Http\Controllers\Api\V1\TripController;
use App\Http\Controllers\Api\V1\TripUserController;

Route::prefix('v1')->group(function () {
    Route::middleware('guest')->group(function () {
        Route::post('/register', [RegisteredUserController::class, 'store']);
        Route::post('/login',    [AuthenticatedSessionController::class, 'store']);
        Route::post('/forgot-password', [PasswordResetLinkController::class, 'store']);
        Route::post('/reset-password',  [NewPasswordController::class, 'store']);
    });

    Route::middleware('auth:sanctum')->scopeBindings()->group(function () {
        Route::get   ('/trips',        [TripController::class, 'index']);
        Route::post  ('/trips',        [TripController::class, 'store']);
        Route::get   ('/trips/{trip}', [TripController::class, 'show']);
        Route::put   ('/trips/{trip}', [TripController::class, 'update']);
        Route::patch ('/trips/{trip}', [TripController::class, 'update']);
        Route::delete('/trips/{trip}', [TripController::class, 'destroy']);

        Route::post('/trips/{trip}/invite', [TripController::class, 'invite']);

        Route::get   ('/trips/{trip}/members',        [TripUserController::class, 'index']);
        Route::put   ('/trips/{trip}/members/{user}', [TripUserController::class, 'update']);
        Route::patch ('/trips/{trip}/members/{user}', [TripUserController::class, 'update']);
        Route::delete('/trips/{trip}/members/{user}', [TripUserController::class, 'destroy']);

        Route::get('/user', fn(Request $r) => $r->user());

        Route::post('/logout', [AuthenticatedSessionController::class, 'destroy']);
    });
});
