<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentAttemptStatus;
use App\Enums\PaymentMethod;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\Payment;
use App\Models\Staff;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * BR29 (customer cancel, FR41, T063), BR54 and BR63 (staff, FR93/FR94, T076),
 * BR26 (closing-time cleanup, FR50, T160). 07.4's named owner of "customer and
 * staff cancel of unpaid orders; resolve stock conflict".
 */
class OrderService
{
    public function __construct(
        private AuditLogger $auditLogger,
        private StripeService $stripe,
        private PaymentService $payments,
    ) {}

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

    /**
     * FR50, BR26, 07.10. Orders never expire on a timer — this job at
     * closing_time is the only thing that cancels them, and BR25 applies to
     * the job as much as to a person: each open Stripe session is expired and
     * then retrieved, so an order paid moments before close is marked paid
     * rather than cancelled.
     *
     * @return array{pending:int, cancelled:int, paid:int, failed:int}
     */
    public function cancelUnpaidAtClose(): array
    {
        $orders = Order::where('status', OrderStatus::PendingPayment)->get();

        $result = ['pending' => $orders->count(), 'cancelled' => 0, 'paid' => 0, 'failed' => 0];

        foreach ($orders as $order) {
            try {
                if ($this->settleLateStripePayment($order)) {
                    $result['paid']++;

                    continue;
                }

                $this->cancelBySystem($order);
                $result['cancelled']++;
            } catch (Throwable $e) {
                $result['failed']++;

                Log::channel('integrations')->error('Closing-time cleanup failed for order.', [
                    'order_id' => $order->order_id,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        return $result;
    }

    /** BR25, BR26: expire the session first, then let Stripe settle the argument. */
    private function settleLateStripePayment(Order $order): bool
    {
        $attempts = $order->payments()
            ->where('method', PaymentMethod::Stripe)
            ->where('status', PaymentAttemptStatus::Pending)
            ->whereNotNull('stripe_session_id')
            ->get();

        foreach ($attempts as $payment) {
            $this->stripe->expireSession($payment->stripe_session_id);

            if ($this->payments->verifyStripePayment($payment)) {
                return true;
            }
        }

        return false;
    }

    /** BR26: the cancel itself, with no actor — audit and history both read `system`. */
    private function cancelBySystem(Order $order): void
    {
        DB::transaction(function () use ($order) {
            $locked = Order::whereKey($order->order_id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== OrderStatus::PendingPayment) {
                return;
            }

            $locked->status->ensureCanTransitionTo(OrderStatus::Cancelled);

            $locked->forceFill([
                'status' => OrderStatus::Cancelled,
                'cancelled_at' => now(),
            ])->save();

            $this->markPendingAttemptsExpired($locked);

            OrderStatusHistory::create([
                'order_id' => $locked->order_id,
                'status_seq' => $locked->statusHistory()->max('status_seq') + 1,
                'status' => 'cancelled',
                'occurred_at' => now(),
                'event_source' => 'system',
            ]);

            $this->auditLogger->log(null, 'order_cancelled', $locked, 'Unpaid at closing time (BR26).');
        });
    }

    /** FR93, BR63, 07.7. Waitstaff or Admin; pending_payment only. */
    public function cancelByStaff(Order $order, Staff $actor, string $reason): Order
    {
        if ($order->status !== OrderStatus::PendingPayment) {
            throw ValidationException::withMessages([
                'order' => 'Only an unpaid order can be cancelled — paid orders need a refund request.',
            ]);
        }

        $this->guardAgainstLatePayment($order, $actor);

        return DB::transaction(function () use ($order, $actor, $reason) {
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

            $this->markPendingAttemptsExpired($locked);

            OrderStatusHistory::create([
                'order_id' => $locked->order_id,
                'status_seq' => $locked->statusHistory()->max('status_seq') + 1,
                'status' => 'cancelled',
                'occurred_at' => now(),
                'event_source' => 'waitstaff',
            ]);

            $this->auditLogger->log($actor, 'order_cancelled', $locked, $reason);

            return $locked->fresh();
        });
    }

    /** FR94, BR54. Both choices clear the flag; the refund choice opens UC26 in the UI, not here. */
    public function resolveConflict(Order $order, Staff $actor, string $resolution): Order
    {
        if (! $order->has_stock_conflict) {
            throw ValidationException::withMessages([
                'order' => 'This order has no stock conflict to resolve.',
            ]);
        }

        $order->forceFill(['has_stock_conflict' => false])->save();

        $this->auditLogger->log($actor, 'stock_conflict_resolved', $order, $resolution);

        return $order->fresh();
    }

    /**
     * 07.7's "expire Stripe session → verify not paid (retrieve)" — BR25 asked
     * in reverse. Runs outside the cancel transaction because markPaid() opens
     * its own and must survive the refusal thrown here.
     */
    private function guardAgainstLatePayment(Order $order, Staff $actor): void
    {
        $attempts = $order->payments()
            ->where('method', PaymentMethod::Stripe)
            ->where('status', PaymentAttemptStatus::Pending)
            ->whereNotNull('stripe_session_id')
            ->get();

        foreach ($attempts as $payment) {
            $this->stripe->expireSession($payment->stripe_session_id);

            if ($this->payments->verifyStripePayment($payment, $actor)) {
                throw ValidationException::withMessages([
                    'order' => 'The customer paid for this order just now — raise a refund request instead.',
                ]);
            }
        }
    }

    private function markPendingAttemptsExpired(Order $order): void
    {
        $order->payments()
            ->where('status', PaymentAttemptStatus::Pending)
            ->get()
            ->each(fn (Payment $payment) => $payment->forceFill(['status' => PaymentAttemptStatus::Expired])->save());
    }

    /** UC12 step 2: the real Stripe call, wired now that StripeService (T070) exists. */
    private function expirePendingStripeAttempts(Order $order): void
    {
        $order->payments()
            ->where('method', PaymentMethod::Stripe)
            ->where('status', PaymentAttemptStatus::Pending)
            ->get()
            ->each(function (Payment $payment): void {
                if ($payment->stripe_session_id !== null) {
                    $this->stripe->expireSession($payment->stripe_session_id);
                }

                $payment->forceFill(['status' => PaymentAttemptStatus::Expired])->save();
            });
    }
}
