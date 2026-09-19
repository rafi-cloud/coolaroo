<?php

use App\Http\Controllers\Admin\AllergenController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\DietaryTagController;
use App\Http\Controllers\Admin\MenuItemController;
use App\Http\Controllers\Admin\StaffAccountController;
use App\Http\Controllers\Customer\Auth\AuthenticatedCustomerController;
use App\Http\Controllers\Customer\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Customer\Auth\EmailVerificationPromptController;
use App\Http\Controllers\Customer\Auth\NewPasswordController;
use App\Http\Controllers\Customer\Auth\PasswordResetLinkController;
use App\Http\Controllers\Customer\Auth\RegisteredCustomerController;
use App\Http\Controllers\Customer\Auth\VerifyEmailController;
use App\Http\Controllers\Customer\ProfileController as CustomerProfileController;
use App\Http\Controllers\Staff\Auth\AuthenticatedStaffController;
use App\Http\Controllers\Staff\ProfileController as StaffProfileController;
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

    Route::get('/profile', [CustomerProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [CustomerProfileController::class, 'update'])->name('profile.update');
});

Route::middleware('guest:staff')->group(function () {
    Route::get('/staff/login', [AuthenticatedStaffController::class, 'create'])->name('staff.login');
    Route::post('/staff/login', [AuthenticatedStaffController::class, 'store'])->middleware('throttle:login');
});

Route::middleware(['auth:staff', 'staff.session'])->group(function () {
    Route::post('/staff/logout', [AuthenticatedStaffController::class, 'destroy'])->name('staff.logout');

    Route::get('/staff/profile', [StaffProfileController::class, 'edit'])->name('staff.profile.edit');
    Route::patch('/staff/profile', [StaffProfileController::class, 'update'])->name('staff.profile.update');
});

Route::prefix('admin')->name('admin.')->middleware(['auth:staff', 'staff.session', 'role:admin'])->group(function () {
    Route::get('/staff', [StaffAccountController::class, 'index'])->name('staff.index');
    Route::get('/staff/create', [StaffAccountController::class, 'create'])->name('staff.create');
    Route::post('/staff', [StaffAccountController::class, 'store'])->name('staff.store');
    Route::get('/staff/{staff}/edit', [StaffAccountController::class, 'edit'])->name('staff.edit');
    Route::patch('/staff/{staff}', [StaffAccountController::class, 'update'])->name('staff.update');
    Route::patch('/staff/{staff}/deactivate', [StaffAccountController::class, 'deactivate'])->name('staff.deactivate');
    Route::patch('/staff/{staff}/reactivate', [StaffAccountController::class, 'reactivate'])->name('staff.reactivate');

    Route::resource('categories', CategoryController::class)->except('show');
    Route::patch('categories/{category}/deactivate', [CategoryController::class, 'deactivate'])->name('categories.deactivate');
    Route::patch('categories/{category}/reactivate', [CategoryController::class, 'reactivate'])->name('categories.reactivate');

    Route::resource('allergens', AllergenController::class)->except('show');
    Route::resource('dietary-tags', DietaryTagController::class)->except('show');

    Route::get('menu-items', [MenuItemController::class, 'index'])->name('menu-items.index');
    Route::get('menu-items/create', [MenuItemController::class, 'create'])->name('menu-items.create');
    Route::post('menu-items', [MenuItemController::class, 'store'])->name('menu-items.store');
    Route::get('menu-items/{menuItem}/edit', [MenuItemController::class, 'edit'])->name('menu-items.edit');
    Route::patch('menu-items/{menuItem}', [MenuItemController::class, 'update'])->name('menu-items.update');
    Route::patch('menu-items/{menuItem}/archive', [MenuItemController::class, 'archive'])->name('menu-items.archive');
    Route::patch('menu-items/{menuItem}/unarchive', [MenuItemController::class, 'unarchive'])->name('menu-items.unarchive');
    Route::patch('menu-items/{menuItem}/toggle-featured', [MenuItemController::class, 'toggleFeatured'])->name('menu-items.toggle-featured');
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
