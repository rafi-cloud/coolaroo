<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\RequestRefundRequest;
use App\Models\Order;
use App\Models\RestaurantTable;
use App\Services\RefundService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Waitstaff, Kitchen or Bar may request — Admin passes
 * via the project-wide Gate::before bypass. (approve/reject/complete)
 * is the Admin::RefundController, not this class.
 */
class RefundRequestController extends Controller
{
    public function __construct(private RefundService $refunds) {}

    /**
     * S46: the table's recent paid orders, what has already been asked for on
     * each line, and the form. Separate from S24 so "Take order" stays the one
     * job that screen does.
     */
    public function index(RestaurantTable $table): View
    {
        Gate::authorize('requestRefund', Order::class);

        $orders = $table->orders()
            ->whereNotNull('paid_at')
            ->where('paid_at', '>=', now()->subHours(RefundService::REQUEST_WINDOW_HOURS))
            ->with(['items.refunds.requestedBy', 'items.refunds.requestedByCustomer'])
            ->latest('paid_at')
            ->get();

        return view('staff.refunds', [
            'table' => $table,
            'orders' => $orders,
            'requestable' => $orders->mapWithKeys(fn (Order $order) => [
                $order->order_id => $this->refunds->canBeRefundRequested($order),
            ])->all(),
        ]);
    }

    public function store(RequestRefundRequest $request, Order $order): RedirectResponse
    {
        Gate::authorize('requestRefund', Order::class);

        $item = $order->items()->findOrFail($request->validated('order_item_id'));

        $this->refunds->request(
            $item,
            (int) $request->validated('quantity'),
            $request->validated('reason'),
            $request->user('staff'),
        );

        return back()->with('status', 'refund-requested');
    }
}
