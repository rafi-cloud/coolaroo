<?php

namespace App\Services;

use App\Enums\OrderItemStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentAttemptStatus;
use App\Enums\PaymentStatus;
use App\Enums\RefundMethod;
use App\Enums\RefundStatus;
use App\Events\OrderStatusChanged;
use App\Events\RefundRequested;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\Refund;
use App\Models\Staff;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Stripe\Exception\ApiErrorException;

/**
 * (request) and (approve/reject/complete/retry).
 */
class RefundService
{
    public function __construct(
        private AuditLogger $auditLogger,
        private StockService $stock,
        private StripeService $stripe,
    ) {}

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

    /** 5.5: cash and manual complete immediately; stripe goes to processing and calls the API. */
    public function approve(
        Refund $refund,
        Staff $admin,
        RefundMethod $method,
        bool $returnToStock,
        ?string $manualReference = null,
    ): Refund {
        if ($refund->status !== RefundStatus::Requested) {
            throw ValidationException::withMessages([
                'refund' => 'Only a requested refund can be approved.',
            ]);
        }

        if ($method === RefundMethod::Stripe && $refund->payment?->stripe_session_id === null) {
            throw ValidationException::withMessages([
                'method' => 'This order was not paid through Stripe.',
            ]);
        }

        $refund->forceFill([
            'method' => $method,
            'return_to_stock' => $returnToStock,
            'manual_reference' => $method === RefundMethod::Manual ? $manualReference : null,
            'processed_by_staff_id' => $admin->staff_id,
        ])->save();

        if ($method !== RefundMethod::Stripe) {
            return $this->complete($refund, $admin);
        }

        $refund->status->ensureCanTransitionTo(RefundStatus::Processing);
        $refund->forceFill(['status' => RefundStatus::Processing])->save();

        return $this->sendToStripe($refund, $admin);
    }

    /** 5.5: requested → rejected. Nothing else about the order changes. */
    public function reject(Refund $refund, Staff $admin, string $reason): Refund
    {
        $refund->status->ensureCanTransitionTo(RefundStatus::Rejected);

        $refund->forceFill([
            'status' => RefundStatus::Rejected,
            'rejection_reason' => $reason,
            'processed_by_staff_id' => $admin->staff_id,
        ])->save();

        $this->auditLogger->log($admin, 'refund_rejected', $refund, $reason);

        return $refund;
    }

    /** 5.5: failed → processing, then a fresh API attempt. */
    public function retry(Refund $refund, Staff $admin): Refund
    {
        $refund->status->ensureCanTransitionTo(RefundStatus::Processing);

        $refund->forceFill([
            'status' => RefundStatus::Processing,
            'processed_by_staff_id' => $admin->staff_id,
        ])->save();

        return $this->sendToStripe($refund, $admin);
    }

    /**
     * The "Check refund status" half of the own logic, for a refund left at
     * processing because this app never saw the API result.
     */
    public function checkProcessing(Refund $refund, Staff $admin): Refund
    {
        if ($refund->status !== RefundStatus::Processing) {
            throw ValidationException::withMessages([
                'refund' => 'Only a processing refund can be checked.',
            ]);
        }

        if ($refund->provider_refund_id === null) {
            return $this->sendToStripe($refund, $admin);
        }

        $remote = $this->stripe->retrieveRefund($refund->provider_refund_id);

        if ($remote->status === 'succeeded') {
            return $this->complete($refund, $admin);
        }

        if ($remote->status === 'failed') {
            return $this->markFailed($refund, 'Stripe reported the refund as failed.');
        }

        return $refund;
    }

    private function sendToStripe(Refund $refund, Staff $admin): Refund
    {
        try {
            $remote = $this->stripe->createRefund($refund);
        } catch (ApiErrorException $e) {
            return $this->markFailed($refund, $e->getMessage());
        }

        $refund->forceFill(['provider_refund_id' => $remote->id])->save();

        if ($remote->status === 'succeeded') {
            return $this->complete($refund, $admin);
        }

        return $refund;
    }

    /** integration failures go to the integrations channel, not the request log. */
    private function markFailed(Refund $refund, string $message): Refund
    {
        $refund->status->ensureCanTransitionTo(RefundStatus::Failed);
        $refund->forceFill(['status' => RefundStatus::Failed])->save();

        Log::channel('integrations')->error('Stripe refund failed', [
            'refund_id' => $refund->refund_id,
            'order_id' => $refund->order_id,
            'message' => $message,
        ]);

        return $refund;
    }

    /**
     * lock order → check total ≤ paid → refunded_qty, payment_status,
     * optional stock return.
     */
    private function complete(Refund $refund, Staff $admin): Refund
    {
        return DB::transaction(function () use ($refund, $admin) {
            $order = Order::whereKey($refund->order_id)->lockForUpdate()->firstOrFail();

            $paidAmount = (float) $order->payments()
                ->where('status', PaymentAttemptStatus::Succeeded)
                ->sum('amount');

            $alreadyRefunded = (float) $order->refunds()
                ->where('status', RefundStatus::Completed)
                ->sum('amount');

            if (round($alreadyRefunded + (float) $refund->amount, 2) > round($paidAmount, 2)) {
                throw ValidationException::withMessages([
                    'refund' => 'This refund would exceed the amount paid for the order.',
                ]);
            }

            $refund->status->ensureCanTransitionTo(RefundStatus::Completed);
            $refund->forceFill([
                'status' => RefundStatus::Completed,
                'completed_at' => now(),
                'processed_by_staff_id' => $admin->staff_id,
            ])->save();

            $item = $refund->orderItem;

            if ($item !== null) {
                $this->applyToLine($refund, $item);
            }

            $this->recalculatePaymentStatus($order, $paidAmount, $alreadyRefunded + (float) $refund->amount);
            $this->cancelOrderIfFullyRefunded($order, $admin);

            $this->auditLogger->log($admin, 'refund_complete', $refund);

            return $refund->refresh();
        });
    }

    /** a line whose whole quantity is refunded is cancelled. gates the stock return. */
    private function applyToLine(Refund $refund, OrderItem $item): void
    {
        $item->forceFill(['refunded_qty' => $item->refunded_qty + $refund->quantity])->save();

        if ($refund->return_to_stock) {
            $menuItem = MenuItem::whereKey($item->item_id)->lockForUpdate()->first();

            if ($menuItem !== null) {
                $this->stock->returnToStock($menuItem, $refund->quantity);
            }
        }

        if ($item->refunded_qty >= $item->quantity && $item->status->canTransitionTo(OrderItemStatus::Cancelled)) {
            $item->forceFill(['status' => OrderItemStatus::Cancelled])->save();
        }
    }

    /** 5.2. Refunded has no outgoing transition, so a fully refunded order stops here. */
    private function recalculatePaymentStatus(Order $order, float $paidAmount, float $refundedAmount): void
    {
        $target = round($refundedAmount, 2) >= round($paidAmount, 2)
            ? PaymentStatus::Refunded
            : PaymentStatus::PartiallyRefunded;

        if ($order->payment_status === $target && $target === PaymentStatus::Refunded) {
            return;
        }

        $order->payment_status->ensureCanTransitionTo($target);
        $order->forceFill(['payment_status' => $target])->save();
    }

    /** 5.1: paid → cancelled, "all lines refunded before start". */
    private function cancelOrderIfFullyRefunded(Order $order, Staff $admin): void
    {
        if ($order->status !== OrderStatus::Paid) {
            return;
        }

        $lines = $order->items()->get();

        if ($lines->isEmpty() || $lines->contains(fn ($line) => $line->status !== OrderItemStatus::Cancelled)) {
            return;
        }

        $order->status->ensureCanTransitionTo(OrderStatus::Cancelled);
        $order->forceFill(['status' => OrderStatus::Cancelled, 'cancelled_at' => now()])->save();

        OrderStatusHistory::create([
            'order_id' => $order->order_id,
            'status_seq' => $order->statusHistory()->max('status_seq') + 1,
            'status' => 'cancelled',
            'occurred_at' => now(),
            'event_source' => 'admin',
        ]);

        $this->auditLogger->log($admin, 'order_cancelled_by_refund', $order);

        event(new OrderStatusChanged($order->refresh()));
    }
}
