<?php

use App\Http\Controllers\Admin\AddOnGroupController;
use App\Http\Controllers\Admin\AddOnOptionController;
use App\Http\Controllers\Admin\AllergenController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\CustomerController as AdminCustomerController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DietaryTagController;
use App\Http\Controllers\Admin\FeedbackController as AdminFeedbackController;
use App\Http\Controllers\Admin\MenuItemController;
use App\Http\Controllers\Admin\MenuItemSizeController;
use App\Http\Controllers\Admin\NoShowController as AdminNoShowController;
use App\Http\Controllers\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Admin\RefundController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\SlotCapacityController;
use App\Http\Controllers\Admin\StaffAccountController;
use App\Http\Controllers\Admin\TableController;
use App\Http\Controllers\Customer\Auth\AuthenticatedCustomerController;
use App\Http\Controllers\Customer\CartController;
use App\Http\Controllers\Customer\CheckoutController;
use App\Http\Controllers\Customer\FeedbackController;
use App\Http\Controllers\Customer\OrderController;
use App\Http\Controllers\Customer\PaymentController;
use App\Http\Controllers\Customer\ReceiptController;
use App\Http\Controllers\Customer\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Customer\Auth\EmailVerificationPromptController;
use App\Http\Controllers\Customer\Auth\NewPasswordController;
use App\Http\Controllers\Customer\Auth\PasswordResetLinkController;
use App\Http\Controllers\Customer\Auth\RegisteredCustomerController;
use App\Http\Controllers\Customer\Auth\VerifyEmailController;
use App\Http\Controllers\Customer\ProfileController as CustomerProfileController;
use App\Http\Controllers\Customer\ReservationController as CustomerReservationController;
use App\Http\Controllers\Public\AiController;
use App\Http\Controllers\Public\AvailabilityController as PublicAvailabilityController;
use App\Http\Controllers\Public\HomeController;
use App\Http\Controllers\Public\MealBuilderController;
use App\Http\Controllers\Public\MenuController;
use App\Http\Controllers\Public\TableScanController;
use App\Http\Controllers\Public\WaiterCallController;
use App\Http\Controllers\Staff\Auth\AuthenticatedStaffController;
use App\Http\Controllers\Staff\Floor\CashPaymentController;
use App\Http\Controllers\Staff\Floor\FloorController;
use App\Http\Controllers\Staff\Floor\ServeController;
use App\Http\Controllers\Staff\Floor\ReservationController as StaffReservationController;
use App\Http\Controllers\Staff\Floor\StaffOrderController;
use App\Http\Controllers\Staff\Floor\TableController as StaffTableController;
use App\Http\Controllers\Staff\Floor\TrustController;
use App\Http\Controllers\Staff\Kds\AvailabilityController;
use App\Http\Controllers\Staff\Kds\StationController;
use App\Http\Controllers\Staff\OrderController as StaffOrderActionsController;
use App\Http\Controllers\Staff\ProfileController as StaffProfileController;
use App\Http\Controllers\Staff\RefundRequestController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/menu', [MenuController::class, 'index'])->name('menu.index');
Route::get('/reservations/availability', [PublicAvailabilityController::class, 'index'])->name('reservations.availability');

Route::get('/meal-builder', [MealBuilderController::class, 'show'])->name('meal-builder');
Route::post('/ai/chat', [AiController::class, 'chat'])->name('ai.chat');
Route::post('/ai/meal-builder', [AiController::class, 'mealBuilder'])->name('ai.meal-builder');

Route::get('/t/{table}/{token}', [TableScanController::class, 'show'])
    ->middleware('signed')
    ->name('table.scan');

Route::post('/t/{table}/switch', [TableScanController::class, 'switchTable'])
    ->middleware('auth:customer')
    ->name('table.scan.switch');

Route::post('/t/{table}/call-waiter', [WaiterCallController::class, 'store'])
    ->name('table.call-waiter');

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

    Route::get('/my/reservations', [CustomerReservationController::class, 'index'])->name('reservations.index');
    Route::post('/reservations', [CustomerReservationController::class, 'store'])
        ->middleware('verified')
        ->name('reservations.store');
    Route::patch('/reservations/{reservation}', [CustomerReservationController::class, 'update'])->name('reservations.update');
    Route::post('/reservations/{reservation}/cancel', [CustomerReservationController::class, 'cancel'])->name('reservations.cancel');

    Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
    Route::get('/orders/{order}', [OrderController::class, 'show'])->name('orders.show');
    Route::get('/orders/{order}/state', [OrderController::class, 'state'])->name('orders.state');
    Route::post('/orders/{order}/cancel', [OrderController::class, 'cancel'])->name('orders.cancel');
    Route::get('/orders/{order}/receipt', [ReceiptController::class, 'show'])->name('orders.receipt');
    Route::get('/orders/{order}/pay', [PaymentController::class, 'show'])->name('orders.pay.show');
    Route::post('/orders/{order}/pay/stripe', [PaymentController::class, 'stripe'])->name('orders.pay.stripe');
    Route::post('/orders/{order}/pay/cash', [PaymentController::class, 'cash'])->name('orders.pay.cash');
    Route::post('/orders/{order}/payment-check', [PaymentController::class, 'check'])->name('orders.pay.check');
    Route::post('/orders/{order}/feedback', [FeedbackController::class, 'store'])->name('orders.feedback.store');
    Route::get('/payment/success', [PaymentController::class, 'return'])->name('payment.success');
    Route::get('/payment/cancelled', [PaymentController::class, 'return'])->name('payment.cancelled');

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

    // No role: restriction — FR51's actor list (Waitstaff, Kitchen, Bar, Admin)
    // is enforced by OrderPolicy::requestRefund() itself, not route middleware.
    Route::post('/staff/orders/{order}/refund-requests', [RefundRequestController::class, 'store'])->name('staff.orders.refund-requests.store');
});

Route::prefix('staff')->middleware(['auth:staff', 'staff.session', 'role:waitstaff'])->group(function () {
    Route::get('/floor', [FloorController::class, 'index'])->name('staff.floor.index');
    Route::get('/floor/state', [FloorController::class, 'state'])->name('staff.floor.state');
    Route::post('/tables/{table}/seat', [StaffTableController::class, 'seat'])->name('staff.tables.seat');
    Route::post('/tables/clear', [StaffTableController::class, 'clear'])->name('staff.tables.clear');
    Route::get('/tables/{table}/order', [StaffOrderController::class, 'index'])->name('staff.tables.order');
    Route::post('/tables/{table}/order', [StaffOrderController::class, 'store'])->name('staff.tables.order.store');
    Route::post('/orders/{order}/serve/{destination}', [ServeController::class, 'store'])->name('staff.orders.serve');
    Route::post('/orders/{order}/cash', [CashPaymentController::class, 'store'])->name('staff.orders.cash.store');
    Route::get('/orders/{order}/stripe-qr', [StaffOrderController::class, 'stripeQr'])->name('staff.orders.stripe-qr');
    Route::post('/orders/{order}/payment-check', [StaffOrderController::class, 'check'])->name('staff.orders.payment-check');
    Route::post('/orders/{order}/cancel', [StaffOrderActionsController::class, 'cancel'])->name('staff.orders.cancel');

    Route::get('/reservations', [StaffReservationController::class, 'index'])->name('staff.reservations.index');
    Route::post('/reservations', [StaffReservationController::class, 'store'])->name('staff.reservations.store');
    Route::post('/reservations/{reservation}/approve', [StaffReservationController::class, 'approve'])->name('staff.reservations.approve');
    Route::post('/reservations/{reservation}/decline', [StaffReservationController::class, 'decline'])->name('staff.reservations.decline');
    Route::post('/reservations/{reservation}/tables', [StaffReservationController::class, 'assign'])->name('staff.reservations.tables.assign');
    Route::delete('/reservations/{reservation}/tables', [StaffReservationController::class, 'unassign'])->name('staff.reservations.tables.unassign');
    Route::post('/reservations/{reservation}/seat', [StaffReservationController::class, 'seat'])->name('staff.reservations.seat');
    Route::post('/reservations/{reservation}/no-show', [StaffReservationController::class, 'noShow'])->name('staff.reservations.no-show');
    Route::get('/customers/{customer}/trust', [TrustController::class, 'show'])->name('staff.customers.trust');
});

Route::prefix('staff')->middleware(['auth:staff', 'staff.session', 'role:kitchen,bar'])->group(function () {
    Route::post('/orders/{order}/stock-conflict/resolve', [StaffOrderActionsController::class, 'resolveConflict'])->name('staff.orders.stock-conflict.resolve');

    Route::get('/kds/{destination}', [StationController::class, 'index'])->name('staff.kds.index');
    Route::get('/kds/{destination}/state', [StationController::class, 'state'])->name('staff.kds.state');
    Route::post('/kds/orders/{order}/{destination}/start', [StationController::class, 'start'])->name('staff.kds.start');
    Route::post('/kds/orders/{order}/{destination}/ready', [StationController::class, 'ready'])->name('staff.kds.ready');
    Route::patch('/kds/orders/{order}/{destination}/eta', [StationController::class, 'adjustEta'])->name('staff.kds.eta');
    Route::patch('/menu-items/{menuItem}/availability', [AvailabilityController::class, 'toggleMenuItem'])->name('staff.menu-items.availability');
    Route::patch('/add-on-options/{option}/availability', [AvailabilityController::class, 'toggleAddOnOption'])->name('staff.add-on-options.availability');
});

Route::prefix('admin')->name('admin.')->middleware(['auth:staff', 'staff.session', 'role:admin'])->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('orders', [AdminOrderController::class, 'index'])->name('orders.index');
    Route::get('orders/{order}', [AdminOrderController::class, 'show'])->name('orders.show');

    Route::get('/refunds', [RefundController::class, 'index'])->name('refunds.index');
    Route::patch('/refunds/{refund}/approve', [RefundController::class, 'approve'])->name('refunds.approve');
    Route::patch('/refunds/{refund}/reject', [RefundController::class, 'reject'])->name('refunds.reject');
    Route::patch('/refunds/{refund}/complete', [RefundController::class, 'complete'])->name('refunds.complete');
    Route::patch('/refunds/{refund}/retry', [RefundController::class, 'retry'])->name('refunds.retry');

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

    Route::get('/customers', [AdminCustomerController::class, 'index'])->name('customers.index');
    Route::get('/customers/{customer}', [AdminCustomerController::class, 'show'])->name('customers.show');
    Route::post('/customers/{customer}/no-shows/{reservation}/clear', [AdminNoShowController::class, 'clear'])->name('customers.no-shows.clear');

    Route::get('/feedback', [AdminFeedbackController::class, 'index'])->name('feedback.index');
    Route::post('/feedback/{feedback}/reply', [AdminFeedbackController::class, 'reply'])->name('feedback.reply');
    Route::patch('/feedback/{feedback}/hide', [AdminFeedbackController::class, 'hide'])->name('feedback.hide');
    Route::patch('/feedback/{feedback}/unhide', [AdminFeedbackController::class, 'unhide'])->name('feedback.unhide');
    Route::patch('/feedback/{feedback}/feature', [AdminFeedbackController::class, 'toggleFeatured'])->name('feedback.feature');

    Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
    Route::patch('reports/ai/toggle', [ReportController::class, 'toggleAi'])->name('reports.ai.toggle');
    Route::get('reports/{type}/export', [ReportController::class, 'export'])->name('reports.export');
    Route::get('reports/{type}', [ReportController::class, 'show'])->name('reports.show');

    Route::get('/settings', [SettingController::class, 'index'])->name('settings.index');
    Route::patch('/settings', [SettingController::class, 'update'])->name('settings.update');
    Route::patch('/settings/toggle/{key}', [SettingController::class, 'toggle'])->name('settings.toggle');
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
