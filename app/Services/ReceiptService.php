<?php

namespace App\Services;

use App\Enums\PaymentAttemptStatus;
use App\Enums\RefundStatus;
use App\Models\Order;
use App\Models\Payment;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;

/**
 * Assembles the receipt data (lines, sale discounts, the succeeded
 * payment's method/rounding/adjustment, completed refunds) and renders it.
 */
class ReceiptService
{
    public function pdf(Order $order): string
    {
        return Pdf::loadView('pdf.receipt', $this->data($order))->output();
    }

    /** @return array{order: Order, lines: Collection, payment: ?Payment, refunds: Collection} */
    public function data(Order $order): array
    {
        $order->loadMissing(['items', 'restaurantTable']);

        return [
            'order' => $order,
            'lines' => $order->items->map(fn ($item) => [
                'item' => $item,
                'onSale' => (float) $item->unit_price < (float) $item->original_unit_price,
            ]),
            'payment' => $order->payments()->where('status', PaymentAttemptStatus::Succeeded)->first(),
            'refunds' => $order->refunds()->where('status', RefundStatus::Completed)->get(),
        ];
    }
}
