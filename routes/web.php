<?php

use App\Http\Controllers\Customer\Auth\AuthenticatedCustomerController;
use App\Http\Controllers\Customer\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Customer\Auth\EmailVerificationPromptController;
use App\Http\Controllers\Customer\Auth\NewPasswordController;
use App\Http\Controllers\Customer\Auth\PasswordResetLinkController;
use App\Http\Controllers\Customer\Auth\RegisteredCustomerController;
use App\Http\Controllers\Customer\Auth\VerifyEmailController;
use App\Http\Controllers\Staff\Auth\AuthenticatedStaffController;
use Illuminate\Support\Facades\Route;

// No '/' route yet — the real homepage lands in T030. Hitting '/' 404s until then.

Route::middleware('guest:customer')->group(function () {
    Route::get('/register', [RegisteredCustomerController::class, 'create'])->name('register');
    Route::post('/register', [RegisteredCustomerController::class, 'store']);

    Route::get('/login', [AuthenticatedCustomerController::class, 'create'])->name('customer.login');
    Route::post('/login', [AuthenticatedCustomerController::class, 'store'])->middleware('throttle:login');

    Route::get('/forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])->name('password.email');

    Route::get('/reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('/reset-password', [NewPasswordController::class, 'store'])->name('password.update');
});

Route::middleware('auth:customer')->group(function () {
    Route::post('/logout', [AuthenticatedCustomerController::class, 'destroy'])->name('logout');

    Route::get('/email/verify', EmailVerificationPromptController::class)->name('verification.notice');

    Route::get('/email/verify/{id}/{hash}', VerifyEmailController::class)
        ->middleware('signed')
        ->name('verification.verify');

    Route::post('/email/verification-notification', EmailVerificationNotificationController::class)
        ->middleware('throttle:6,1')
        ->name('verification.send');
});

Route::middleware('guest:staff')->group(function () {
    Route::get('/staff/login', [AuthenticatedStaffController::class, 'create'])->name('staff.login');
    Route::post('/staff/login', [AuthenticatedStaffController::class, 'store'])->middleware('throttle:login');
});

Route::middleware(['auth:staff', 'staff.session'])->group(function () {
    Route::post('/staff/logout', [AuthenticatedStaffController::class, 'destroy'])->name('staff.logout');
});

//have to delete this block when the real pages done.
if (app()->isLocal()) {
    Route::prefix('_preview')->group(function () {
        Route::view('public', 'preview.public');
        Route::view('customer', 'preview.customer');
        Route::view('customer-table', 'preview.customer-table');
        Route::view('staff', 'preview.staff');
        Route::view('admin', 'preview.admin');
    });
}
