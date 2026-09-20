<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\CancelOrderRequest;
use App\Http\Requests\Staff\ResolveStockConflictRequest;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

/**
 * FR93 (UC40, BR63) and FR94 (UC41, BR54). 07.6 names this class for both,
 * separately from Staff\Floor\StaffOrderController (T073, FR42).
 */
class OrderController extends Controller
{
    public function __construct(private OrderService $orders) {}

    public function cancel(CancelOrderRequest $request, Order $order): RedirectResponse
    {
        Gate::authorize('cancel', $order);

        $this->orders->cancelByStaff($order, $request->user('staff'), $request->validated('reason'));

        return back()->with('status', 'order-cancelled');
    }

    public function resolveConflict(ResolveStockConflictRequest $request, Order $order): RedirectResponse
    {
        Gate::authorize('resolveStockConflict', Order::class);

        $this->orders->resolveConflict($order, $request->user('staff'), $request->validated('resolution'));

        return back()->with('status', 'conflict-resolved');
    }
}
