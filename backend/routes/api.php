<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\TripController;
use App\Http\Controllers\Api\V1\TripUserController;

Route::prefix('v1')->group(function () {
    Route::post('/register', [\App\Http\Controllers\Auth\RegisteredUserController::class, 'store']);
    Route::post('/login',    [\App\Http\Controllers\Auth\AuthenticatedSessionController::class, 'store']);
});

Route::prefix('v1')
    ->middleware('auth:sanctum')
    ->scopeBindings()
    ->group(function () {

        Route::get   ('/trips',          [TripController::class, 'index']);
        Route::post  ('/trips',          [TripController::class, 'store']);
        Route::get   ('/trips/{trip}',   [TripController::class, 'show']);
        Route::put   ('/trips/{trip}',   [TripController::class, 'update']);
        Route::patch ('/trips/{trip}',   [TripController::class, 'update']);
        Route::delete('/trips/{trip}',   [TripController::class, 'destroy']);

        Route::post('/trips/{trip}/invite', [TripController::class, 'invite']);

        Route::get   ('/trips/{trip}/members',                    [TripUserController::class, 'index']);
        Route::put   ('/trips/{trip}/members/{user}',             [TripUserController::class, 'update']);  // смена роли
        Route::patch ('/trips/{trip}/members/{user}',             [TripUserController::class, 'update']);
        Route::delete('/trips/{trip}/members/{user}',             [TripUserController::class, 'destroy']); // удалить участника

        Route::get('/user', fn(\Illuminate\Http\Request $r) => $r->user());
    });

Route::prefix('v1')->middleware('auth:sanctum')->post('/logout', [\App\Http\Controllers\Auth\AuthenticatedSessionController::class, 'destroy']);
