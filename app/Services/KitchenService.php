<?php

namespace App\Services;

use App\Enums\Destination;
use App\Enums\OrderItemStatus;
use App\Enums\OrderStatus;
use App\Events\OrderLinesUpdated;
use App\Events\OrderStatusChanged;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\Staff;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * FR58, FR60, BR28, 5.1, 5.3. Lines of one order at one station move together;
 * the order's own status is always derived, never set by hand.
 */
class KitchenService
{
    /** 5.1's order ladder, in order — derivation walks it one legal step at a time. */
    private const LADDER = [
        OrderStatus::Paid,
        OrderStatus::Preparing,
        OrderStatus::Ready,
        OrderStatus::Served,
    ];

    public function __construct(private AuditLogger $auditLogger) {}

    /** FR58: Start. */
    public function start(Order $order, Destination $destination, Staff $actor): Order
    {
        return $this->advanceLines($order, $destination, $actor, OrderItemStatus::Pending, OrderItemStatus::Preparing);
    }

    /** FR58: Ready. */
    public function ready(Order $order, Destination $destination, Staff $actor): Order
    {
        return $this->advanceLines($order, $destination, $actor, OrderItemStatus::Preparing, OrderItemStatus::Ready);
    }

    /** FR60. Waitstaff only — no per-station restriction, unlike Start/Ready. */
    public function serve(Order $order, Destination $destination, Staff $actor): Order
    {
        return $this->advanceLines($order, $destination, $actor, OrderItemStatus::Ready, OrderItemStatus::Served);
    }

    private function advanceLines(
        Order $order,
        Destination $destination,
        Staff $actor,
        OrderItemStatus $from,
        OrderItemStatus $to,
    ): Order {
        return DB::transaction(function () use ($order, $destination, $actor, $from, $to) {
            $locked = Order::whereKey($order->order_id)->lockForUpdate()->firstOrFail();

            $lines = $locked->items()
                ->where('destination', $destination)
                ->where('status', $from)
                ->get();

            if ($lines->isEmpty()) {
                throw ValidationException::withMessages([
                    'order' => "No {$from->value} lines for this station.",
                ]);
            }

            $lines->each(function (OrderItem $line) use ($to) {
                $line->status->ensureCanTransitionTo($to);

                $line->forceFill(array_filter([
                    'status' => $to,
                    'prepared_at' => $to === OrderItemStatus::Ready ? now() : null,
                ]))->save();
            });

            $this->deriveOrderStatus($locked, $actor);

            $this->auditLogger->log($actor, "lines_{$to->value}", $locked);

            $locked->refresh();

            event(new OrderLinesUpdated($locked, $destination));

            return $locked;
        });
    }

    /**
     * BR28. The single source of truth for an order's own status. Cancelled
     * lines are not "active" — a refunded line must not hold an order back.
     */
    public function deriveOrderStatus(Order $order, ?Staff $actor = null): Order
    {
        $active = $order->items()
            ->where('status', '!=', OrderItemStatus::Cancelled)
            ->get();

        if ($active->isEmpty()) {
            return $order;
        }

        $target = $this->targetStatus($active);

        if ($target === null) {
            return $order;
        }

        return $this->climbTo($order, $target, $actor);
    }

    /** BR28's three clauses, most-advanced first. */
    private function targetStatus(Collection $active): ?OrderStatus
    {
        if ($active->every(fn (OrderItem $line) => $line->status === OrderItemStatus::Served)) {
            return OrderStatus::Served;
        }

        if ($active->every(fn (OrderItem $line) => in_array($line->status, [OrderItemStatus::Ready, OrderItemStatus::Served], true))) {
            return OrderStatus::Ready;
        }

        if ($active->contains(fn (OrderItem $line) => $line->status === OrderItemStatus::Preparing)) {
            return OrderStatus::Preparing;
        }

        return null;
    }

    /** Walks 5.1's ladder a step at a time so no transition map is bypassed. */
    private function climbTo(Order $order, OrderStatus $target, ?Staff $actor): Order
    {
        $currentIndex = array_search($order->status, self::LADDER, true);
        $targetIndex = array_search($target, self::LADDER, true);

        if ($currentIndex === false || $targetIndex === false || $targetIndex <= $currentIndex) {
            return $order;
        }

        for ($step = $currentIndex + 1; $step <= $targetIndex; $step++) {
            $next = self::LADDER[$step];

            $order->status->ensureCanTransitionTo($next);

            $order->forceFill([
                'status' => $next,
                ...$this->timestampFor($next),
            ])->save();

            OrderStatusHistory::create([
                'order_id' => $order->order_id,
                'status_seq' => $order->statusHistory()->max('status_seq') + 1,
                'status' => $next->value,
                'occurred_at' => now(),
                'event_source' => $actor?->role->role_name ?? 'system',
            ]);

            $order->refresh();
        }

        event(new OrderStatusChanged($order));

        return $order;
    }

    /** @return array<string, Carbon> */
    private function timestampFor(OrderStatus $status): array
    {
        return match ($status) {
            OrderStatus::Preparing => ['started_at' => now()],
            OrderStatus::Ready => ['ready_at' => now()],
            OrderStatus::Served => ['served_at' => now()],
            default => [],
        };
    }
}
