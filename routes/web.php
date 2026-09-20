<?php

use App\Http\Controllers\Admin\AddOnGroupController;
use App\Http\Controllers\Admin\AddOnOptionController;
use App\Http\Controllers\Admin\AllergenController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\DietaryTagController;
use App\Http\Controllers\Admin\MenuItemController;
use App\Http\Controllers\Admin\MenuItemSizeController;
use App\Http\Controllers\Admin\SlotCapacityController;
use App\Http\Controllers\Admin\StaffAccountController;
use App\Http\Controllers\Admin\TableController;
use App\Http\Controllers\Customer\Auth\AuthenticatedCustomerController;
use App\Http\Controllers\Customer\CartController;
use App\Http\Controllers\Customer\CheckoutController;
use App\Http\Controllers\Customer\OrderController;
use App\Http\Controllers\Customer\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Customer\Auth\EmailVerificationPromptController;
use App\Http\Controllers\Customer\Auth\NewPasswordController;
use App\Http\Controllers\Customer\Auth\PasswordResetLinkController;
use App\Http\Controllers\Customer\Auth\RegisteredCustomerController;
use App\Http\Controllers\Customer\Auth\VerifyEmailController;
use App\Http\Controllers\Customer\ProfileController as CustomerProfileController;
use App\Http\Controllers\Public\TableScanController;
use App\Http\Controllers\Staff\Auth\AuthenticatedStaffController;
use App\Http\Controllers\Staff\ProfileController as StaffProfileController;
use Illuminate\Support\Facades\Route;

// No '/' route yet — the real homepage lands in T030. Hitting '/' 404s until then.

Route::get('/t/{table}/{token}', [TableScanController::class, 'show'])
    ->middleware('signed')
    ->name('table.scan');

Route::post('/t/{table}/switch', [TableScanController::class, 'switchTable'])
    ->middleware('auth:customer')
    ->name('table.scan.switch');

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

    Route::get('/orders/{order}', [OrderController::class, 'show'])->name('orders.show');
    Route::get('/orders/{order}/state', [OrderController::class, 'state'])->name('orders.state');
    Route::post('/orders/{order}/cancel', [OrderController::class, 'cancel'])->name('orders.cancel');

    Route::middleware(['table.context', 'qr.ordering.enabled'])->group(function () {
        Route::get('/cart', [CartController::class, 'index'])->name('cart.index');
        Route::post('/cart/lines', [CartController::class, 'store'])->name('cart.lines.store');
        Route::patch('/cart/lines/{line}', [CartController::class, 'update'])->name('cart.lines.update');
        Route::delete('/cart/lines/{line}', [CartController::class, 'destroy'])->name('cart.lines.destroy');
        Route::delete('/cart', [CartController::class, 'clear'])->name('cart.clear');
        Route::post('/checkout', [CheckoutController::class, 'store'])->name('checkout.store')->middleware('throttle:checkout');
    });
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

    Route::post('menu-items/{menuItem}/sizes', [MenuItemSizeController::class, 'store'])->name('menu-items.sizes.store');
    Route::patch('menu-items/{menuItem}/sizes/{size}', [MenuItemSizeController::class, 'update'])->name('menu-items.sizes.update');
    Route::delete('menu-items/{menuItem}/sizes/{size}', [MenuItemSizeController::class, 'destroy'])->name('menu-items.sizes.destroy');

    Route::post('menu-items/{menuItem}/groups', [AddOnGroupController::class, 'store'])->name('menu-items.groups.store');
    Route::patch('menu-items/{menuItem}/groups/{group}', [AddOnGroupController::class, 'update'])->name('menu-items.groups.update');
    Route::delete('menu-items/{menuItem}/groups/{group}', [AddOnGroupController::class, 'destroy'])->name('menu-items.groups.destroy');

    Route::post('menu-items/{menuItem}/groups/{group}/options', [AddOnOptionController::class, 'store'])->name('menu-items.groups.options.store');
    Route::patch('menu-items/{menuItem}/groups/{group}/options/{option}', [AddOnOptionController::class, 'update'])->name('menu-items.groups.options.update');
    Route::delete('menu-items/{menuItem}/groups/{group}/options/{option}', [AddOnOptionController::class, 'destroy'])->name('menu-items.groups.options.destroy');

    Route::resource('slots', SlotCapacityController::class)->except('show')->parameters(['slots' => 'slot']);

    Route::resource('tables', TableController::class)->except(['show', 'destroy'])->parameters(['tables' => 'table']);
    Route::patch('tables/{table}/deactivate', [TableController::class, 'deactivate'])->name('tables.deactivate');
    Route::patch('tables/{table}/reactivate', [TableController::class, 'reactivate'])->name('tables.reactivate');
    Route::patch('tables/{table}/status', [TableController::class, 'overrideStatus'])->name('tables.status');
    Route::patch('tables/{table}/qr/regenerate', [TableController::class, 'regenerateQr'])->name('tables.qr.regenerate');
    Route::get('tables/{table}/qr.png', [TableController::class, 'qr'])->name('tables.qr');
    Route::get('tables/{table}/qr.pdf', [TableController::class, 'qrPdf'])->name('tables.qr.pdf');
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
