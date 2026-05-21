<?php

use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\Auth\EmailVerificationController;
use App\Http\Controllers\Api\V1\Auth\PasswordResetController;

use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

    // Auth
    Route::prefix('auth')->group(function () {
        Route::post('register', [AuthController::class, 'register'])->middleware('throttle:10,1');
        Route::post('login',    [AuthController::class, 'login'])->middleware('throttle:5,1');

        // Email Verification
        Route::get('email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
            ->middleware('signed')
            ->middleware('throttle:5,1')
            ->name('verification.verify');

        Route::middleware('auth:sanctum')->group(function () {
            Route::post('logout', [AuthController::class, 'logout']);
            Route::get('me',      [AuthController::class, 'me']);

            // Email Resend auth necessary
            Route::post('email/resend', [EmailVerificationController::class, 'resend'])->middleware('throttle:3,1');
        });

        // Password Reset
        Route::post('password/forgot', [PasswordResetController::class, 'forgot'])->middleware('throttle:5,1');
        Route::post('password/reset',  [PasswordResetController::class, 'reset'])->middleware('throttle:5,1');
    });

});