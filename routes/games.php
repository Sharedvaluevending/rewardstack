<?php

use App\Http\Controllers\PlayController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Game API Routes (Anonymous Access)
|--------------------------------------------------------------------------
|
| Routes for public game playing that don't require authentication
| or CSRF protection. These are separate from web routes.
|
*/

Route::middleware('throttle:game')->group(function () {
    Route::post('/api/play/{code}/game/{game}/start', [PlayController::class, 'startSession']);
    Route::post('/api/play/session/{session}/play', [PlayController::class, 'startPlaying']);
    Route::post('/api/play/session/{session}/score', [PlayController::class, 'submitScore']);
    Route::post('/api/game/verify-location', [PlayController::class, 'verifyLocation']);
});