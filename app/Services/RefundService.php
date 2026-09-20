<?php

namespace App\Services;

use App\Enums\PaymentAttemptStatus;
use App\Enums\RefundMethod;
use App\Enums\RefundStatus;
use App\Events\RefundRequested;
use App\Models\OrderItem;
use App\Models\Refund;
use App\Models\Staff;
use Illuminate\Validation\ValidationException;

/**
 * FR51, BR27. request() only for now — T075 adds approve/reject/complete/
 * retry (FR52) to this same class.
 */
class RefundService
{
    public function __construct(private AuditLogger $auditLogger)
    {
    }

    /** payment.method (PaymentMethod) and refund.method (RefundMethod) are separate enums; mapped via ::from() below. */
    public function request(OrderItem $item, int $quantity, string $reason, Staff $actor): Refund
    {
        $order = $item->order;
        $succeededPayment = $order->payments()->where('status', PaymentAttemptStatus::Succeeded)->first();

        if ($succeededPayment === null) {
            throw ValidationException::withMessages([
                'order' => 'This order has not been paid yet.',
            ]);
        }

        $alreadyClaimed = $item->refunded_qty + $item->refunds()
            ->whereIn('status', [RefundStatus::Requested, RefundStatus::Processing])
            ->sum('quantity');

        $remaining = $item->quantity - $alreadyClaimed;

        if ($quantity < 1 || $quantity > $remaining) {
            throw ValidationException::withMessages([
                'quantity' => $remaining > 0
                    ? "Only {$remaining} unit(s) of this line can still be refunded."
                    : 'This line has already been fully refunded or requested.',
            ]);
        }

        $perUnitAmount = round((float) $item->line_total / $item->quantity, 2);

        $refund = Refund::create([
            'order_id' => $order->order_id,
            'order_item_id' => $item->order_item_id,
            'payment_id' => $succeededPayment->payment_id,
            'requested_by_staff_id' => $actor->staff_id,
            'method' => RefundMethod::from($succeededPayment->method->value),
            'quantity' => $quantity,
            'amount' => round($perUnitAmount * $quantity, 2),
            'reason' => $reason,
        ]);

        $this->auditLogger->log($actor, 'refund_requested', $refund);

        event(new RefundRequested($refund));

        return $refund;
    }
}
