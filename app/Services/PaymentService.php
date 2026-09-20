<?php

namespace App\Services;

use App\Enums\Destination;
use App\Enums\OrderStatus;
use App\Enums\PaymentAttemptStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\TableStatus;
use App\Events\OrderPaid;
use App\Events\OrderStatusChanged;
use App\Events\StockConflictDetected;
use App\Models\Customer;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\Payment;
use App\Models\RestaurantTable;
use App\Models\Staff;
use App\Models\Visit;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * FR55, 07.7, BR01, BR10, BR25, BR30, BR54. Shared by Stripe (T070) and cash
 * (T072) — both create a Payment row and hand it here; this class builds
 * the transaction itself.
 */
class PaymentService
{
    public function __construct(
        private StockService $stock,
        private TableStatusService $tableStatus,
        private AuditLogger $auditLogger,
        private StripeService $stripe,
        private EtaService $eta,
    ) {}

    /** BR25: the one place any caller (customer or staff, T073) turns a retrieved session into "paid" or not. */
    public function verifyStripePayment(Payment $payment, Staff|Customer|null $actor = null): bool
    {
        $session = $this->stripe->retrieveSession($payment->stripe_session_id);

        if ($session->payment_status === 'paid') {
            $this->markPaid($payment, $actor);

            return true;
        }

        return false;
    }

    /**
     * BR25, FR47, 07.10. The reconcile job's half of "no webhooks": every
     * pending Stripe attempt is re-read from Stripe, so a customer who paid
     * and then closed the tab still reaches the kitchen. An attempt Stripe
     * has expired is closed off locally — the order itself stays
     * pending_payment until the closing-time cleanup (BR26).
     *
     * @return array{checked:int, paid:int, expired:int, failed:int}
     */
    public function reconcilePendingStripePayments(): array
    {
        $attempts = Payment::query()
            ->where('method', PaymentMethod::Stripe)
            ->where('status', PaymentAttemptStatus::Pending)
            ->whereNotNull('stripe_session_id')
            ->get();

        $result = ['checked' => $attempts->count(), 'paid' => 0, 'expired' => 0, 'failed' => 0];

        foreach ($attempts as $payment) {
            try {
                $session = $this->stripe->retrieveSession($payment->stripe_session_id);

                if ($session->payment_status === 'paid') {
                    $this->markPaid($payment);
                    $result['paid']++;

                    continue;
                }

                if ($session->status === 'expired') {
                    $payment->forceFill(['status' => PaymentAttemptStatus::Expired])->save();
                    $result['expired']++;
                }
            } catch (Throwable $e) {
                $result['failed']++;

                Log::channel('integrations')->error('Stripe reconcile failed for payment attempt.', [
                    'payment_id' => $payment->payment_id,
                    'stripe_session_id' => $payment->stripe_session_id,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        return $result;
    }

    public function markPaid(Payment $payment, Staff|Customer|null $actor = null): Order
    {
        return DB::transaction(function () use ($payment, $actor) {
            $lockedPayment = Payment::whereKey($payment->payment_id)->lockForUpdate()->firstOrFail();

            if ($lockedPayment->status === PaymentAttemptStatus::Succeeded) {
                return Order::findOrFail($lockedPayment->order_id);
            }

            $order = Order::whereKey($lockedPayment->order_id)->firstOrFail();
            $items = $order->items()->with('menuItem')->get();

            $this->deductStock($order, $items);

            $lockedPayment->forceFill(['status' => PaymentAttemptStatus::Succeeded, 'paid_at' => now()])->save();

            $order->forceFill([
                'status' => OrderStatus::Paid,
                'payment_status' => PaymentStatus::Paid,
                'paid_at' => now(),
                'kitchen_eta_at' => $this->eta->estimate($order, $items, Destination::Kitchen),
                'bar_eta_at' => $this->eta->estimate($order, $items, Destination::Bar),
            ])->save();

            $table = $order->restaurantTable()->lockForUpdate()->first();
            $visit = $this->openVisit($table);
            $order->update(['visit_id' => $visit->visit_id]);

            if ($table->status !== TableStatus::Occupied) {
                $this->tableStatus->transition($table, TableStatus::Occupied);
            }

            OrderStatusHistory::create([
                'order_id' => $order->order_id,
                'status_seq' => $order->statusHistory()->max('status_seq') + 1,
                'status' => 'paid',
                'occurred_at' => now(),
                'event_source' => $lockedPayment->method === PaymentMethod::Cash ? 'waitstaff' : 'stripe',
            ]);

            $this->auditLogger->log($actor, 'payment_succeeded', $order);

            $order->refresh();

            event(new OrderPaid($order));
            event(new OrderStatusChanged($order));

            if ($order->has_stock_conflict) {
                foreach ($items->pluck('destination')->unique() as $destination) {
                    event(new StockConflictDetected($order, $destination));
                }
            }

            return $order;
        });
    }

    /** BR09/BR10/BR54: exact deduction, flagged (never blocked) on conflict. */
    private function deductStock(Order $order, Collection $items): void
    {
        $quantityByItem = [];

        foreach ($items as $line) {
            $quantityByItem[$line->item_id] = ($quantityByItem[$line->item_id] ?? 0) + $line->quantity;
        }

        if (empty($quantityByItem)) {
            return;
        }

        $menuItems = MenuItem::whereIn('item_id', array_keys($quantityByItem))
            ->orderBy('item_id')
            ->lockForUpdate()
            ->get()
            ->keyBy('item_id');

        $conflict = false;

        foreach ($quantityByItem as $itemId => $quantity) {
            if (! $this->stock->deduct($menuItems[$itemId], $quantity)) {
                $conflict = true;
            }
        }

        if ($conflict) {
            $order->update(['has_stock_conflict' => true]);
        }
    }

    /** BR01, 06.4.16: opened_by_staff_id stays NULL — no staff is present for a QR payment. */
    private function openVisit(RestaurantTable $table): Visit
    {
        $visit = Visit::where('table_id', $table->table_id)->whereNull('closed_at')->first();

        if ($visit === null) {
            return Visit::create(['table_id' => $table->table_id, 'opened_at' => now()]);
        }

        if ($visit->opened_at === null) {
            $visit->update(['opened_at' => now()]);
        }

        return $visit;
    }
}
