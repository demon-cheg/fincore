<?php

use App\Http\Controllers\Api\AccountController;
use App\Http\Controllers\Api\AuthController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\TransferController;

Route::post('/auth/token', [AuthController::class, 'store'])
    ->middleware('throttle:30,1');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/accounts', [AccountController::class, 'index']);

    Route::post('/transfers', [TransferController::class, 'store'])
        ->middleware('throttle:30,1');

    Route::delete('/auth/token', [AuthController::class, 'destroy']);
});