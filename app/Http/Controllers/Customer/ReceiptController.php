<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\ReceiptService;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

/**
 * Reuses OrderPolicy::view() — a receipt is just
 * another read of the same order, gated by the same "own order" rule.
 */
class ReceiptController extends Controller
{
    public function __construct(private ReceiptService $receipts) {}

    public function show(Order $order): Response
    {
        Gate::authorize('view', $order);

        return response($this->receipts->pdf($order), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="receipt-'.$order->order_number.'.pdf"',
        ]);
    }
}
