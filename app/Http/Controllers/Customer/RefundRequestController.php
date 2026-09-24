<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\RequestRefundRequest;
use App\Models\Order;
use App\Services\RefundService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

/**
 * FR51: the customer half of raising a refund request. The request itself is
 * only ever a request — RefundService::approve() is Admin's, and unchanged.
 */
class RefundRequestController extends Controller
{
    public function __construct(private RefundService $refunds) {}

    public function store(RequestRefundRequest $request, Order $order): RedirectResponse
    {
        Gate::authorize('requestRefundAsCustomer', $order);

        $item = $order->items()->findOrFail($request->validated('order_item_id'));

        $this->refunds->request(
            $item,
            (int) $request->validated('quantity'),
            $request->validated('reason'),
            $request->user('customer'),
        );

        return redirect()->route('orders.show', $order)->with('status', 'refund-requested');
    }
}
