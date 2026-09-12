<?php

use App\Http\Controllers\Api\AccountController;
use App\Http\Controllers\Api\AuthController;
use Illuminate\Support\Facades\Route;

Route::post('/auth/token', [AuthController::class, 'store'])
    ->middleware('throttle:4,1');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/accounts', [AccountController::class, 'index']);

    Route::delete('/auth/token', [AuthController::class, 'destroy']);
});