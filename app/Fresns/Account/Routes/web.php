<?php

/*
 * Fresns (https://fresns.org)
 * Copyright (C) 2021-Present Jevan Tang
 * Released under the Apache-2.0 License.
 */

use App\Fresns\Account\Http\Controllers\ApiController;
use App\Fresns\Account\Http\Controllers\WebController;
use App\Fresns\Account\Http\Middleware\CheckAccessToken;
use App\Fresns\Account\Http\Middleware\VerifyAccountToken;
use Illuminate\Support\Facades\Route;

Route::name('account-center.')->prefix('account-center')->group(function () {
    Route::middleware(CheckAccessToken::class)->group(function () {
        Route::get('/', [WebController::class, 'index'])->name('index');
        Route::get('sign-up', [WebController::class, 'signUp'])->name('sign-up');
        Route::get('login', [WebController::class, 'login'])->name('login');
        Route::get('reset-password', [WebController::class, 'resetPassword'])->name('reset-password');
    });

    Route::name('api.')->prefix('api')->group(function () {
        Route::post('make-access-token', [ApiController::class, 'makeAccessToken'])->name('make-access-token');
        Route::post('guest-send-verify-code', [ApiController::class, 'guestSendVerifyCode'])->name('guest-send-verify-code');
        Route::post('login', [ApiController::class, 'login'])->name('login');
        Route::post('sign-up', [ApiController::class, 'signUp'])->name('sign-up');
        Route::patch('reset-password', [ApiController::class, 'resetPassword'])->name('reset-password');

        Route::middleware(VerifyAccountToken::class)->group(function () {
            Route::post('send-verify-code', [ApiController::class, 'sendVerifyCode'])->name('send-verify-code');
            Route::post('check-verify-code', [ApiController::class, 'checkVerifyCode'])->name('check-verify-code');
            Route::patch('update', [ApiController::class, 'update'])->name('update');
            Route::post('apply-delete', [ApiController::class, 'applyDelete'])->name('apply.delete');
            Route::post('revoke-delete', [ApiController::class, 'revokeDelete'])->name('revoke.delete');
        });
    });
});
