<?php

use App\Http\Controllers\Api\AdController;
use App\Http\Controllers\Api\DailyController;
use App\Http\Controllers\Api\DeviceAuthController;
use App\Http\Controllers\Api\GameController;
use App\Http\Controllers\Api\MeController;
use App\Http\Controllers\Api\ReferralController;
use App\Http\Controllers\Api\TaskController;
use App\Http\Controllers\Api\WalletController;
use App\Http\Controllers\Api\WithdrawController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — Merge Zoo
|--------------------------------------------------------------------------
*/

// Public
Route::get('/config', [MeController::class, 'config']);
Route::post('/auth/device', DeviceAuthController::class)->middleware('throttle:20,1');

// Authenticated (Sanctum token per device)
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [MeController::class, 'show']);

    Route::prefix('game')->group(function () {
        Route::post('/session/start', [GameController::class, 'start'])->middleware('throttle:60,1');
        Route::post('/session/end', [GameController::class, 'end'])->middleware('throttle:60,1');
    });

    // Daily check-in ladder
    Route::get('/daily', [DailyController::class, 'index']);
    Route::post('/daily/claim', [DailyController::class, 'claim'])->middleware('throttle:10,1');

    // Tasks
    Route::get('/tasks', [TaskController::class, 'index']);
    Route::post('/tasks/{key}/claim', [TaskController::class, 'claim'])->middleware('throttle:30,1');

    // Wallet: coins -> cash
    Route::post('/wallet/convert', [WalletController::class, 'convert'])->middleware('throttle:20,1');

    // Ads: ordered waterfall + provisional rewarded reward
    Route::get('/ads/waterfall', [AdController::class, 'waterfall']);
    Route::post('/ads/reward', [AdController::class, 'reward'])->middleware('throttle:30,1');

    // Payouts
    Route::get('/withdraw/methods', [WithdrawController::class, 'methods']);
    Route::get('/withdraw/history', [WithdrawController::class, 'history']);
    Route::post('/withdraw', [WithdrawController::class, 'store'])->middleware('throttle:10,1');

    // Invite
    Route::get('/referral', [ReferralController::class, 'show']);
});
