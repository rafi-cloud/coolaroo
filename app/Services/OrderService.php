<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentAttemptStatus;
use App\Enums\PaymentMethod;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * BR29, BR54, BR63. Customer cancel only for now (FR41, T063) — T076 adds
 * the staff cancel and stock-conflict-resolve half 07.4 already names this
 * class for.
 */
class OrderService
{
    public function __construct(private AuditLogger $auditLogger)
    {
    }

    /** BR29: pending_payment only. UC12 step 2's local half — see class docblock. */
    public function cancelUnpaid(Order $order, Customer $actor): Order
    {
        return DB::transaction(function () use ($order, $actor) {
            $locked = Order::whereKey($order->order_id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== OrderStatus::PendingPayment) {
                throw ValidationException::withMessages([
                    'order' => 'This order has already moved past pending payment.',
                ]);
            }

            $locked->status->ensureCanTransitionTo(OrderStatus::Cancelled);

            $locked->forceFill([
                'status' => OrderStatus::Cancelled,
                'cancelled_at' => now(),
            ])->save();

            $this->expirePendingStripeAttempts($locked);

            OrderStatusHistory::create([
                'order_id' => $locked->order_id,
                'status_seq' => $locked->statusHistory()->max('status_seq') + 1,
                'status' => 'cancelled',
                'occurred_at' => now(),
                'event_source' => 'customer',
            ]);

            $this->auditLogger->log($actor, 'order_cancelled', $locked);

            return $locked->fresh();
        });
    }

    /** UC12 step 2: no live Stripe call yet — StripeService is T070. */
    private function expirePendingStripeAttempts(Order $order): void
    {
        $order->payments()
            ->where('method', PaymentMethod::Stripe)
            ->where('status', PaymentAttemptStatus::Pending)
            ->get()
            ->each(fn (Payment $payment) => $payment->forceFill(['status' => PaymentAttemptStatus::Expired])->save());
    }
}
