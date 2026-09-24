<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\RequestRefundRequest;
use App\Models\Order;
use App\Services\RefundService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

/**
 * Waitstaff, Kitchen or Bar may request — Admin passes
 * via the project-wide Gate::before bypass. (approve/reject/complete)
 * is the Admin::RefundController, not this class.
 */
class RefundRequestController extends Controller
{
    public function __construct(private RefundService $refunds) {}

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
