<?php

namespace App\Services;

use App\Enums\Destination;
use App\Enums\OrderItemStatus;
use App\Enums\OrderStatus;
use App\Events\OrderLinesUpdated;
use App\Models\Order;
use App\Models\Setting;
use App\Models\Staff;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/** Calculation (the original formula, relocated here) and staff adjustment. */
class EtaService
{
    public function __construct(private AuditLogger $auditLogger) {}

    /** longest prep time at the station + orders ahead x avg minutes per order. */
    public function estimate(Order $order, Collection $items, Destination $destination): ?Carbon
    {
        $lines = $items->filter(fn ($line) => $line->destination === $destination);

        if ($lines->isEmpty()) {
            return null;
        }

        $longestPrep = $lines->max(fn ($line) => $line->menuItem->prep_minutes);

        $settingKey = $destination === Destination::Kitchen ? 'avg_ticket_minutes_kitchen' : 'avg_ticket_minutes_bar';
        $default = $destination === Destination::Kitchen ? 8 : 3;
        $avgMinutes = (int) (Setting::find($settingKey)?->setting_value ?? $default);

        // BR30 counts "orders ahead in that station queue", and FR56 defines
        // that queue as paid orders' active lines. Counting the lines alone
        // also swept up every cancelled and served order whose lines were never
        // moved off pending — 22 of them on this database, all of them finished
        // days earlier, which is what pushed a burger's ETA three hours out.
        $queueAhead = Order::query()
            ->whereIn('status', [OrderStatus::Paid, OrderStatus::Preparing, OrderStatus::Ready])
            ->whereKeyNot($order->order_id)
            ->whereHas('items', fn ($query) => $query
                ->where('destination', $destination->value)
                ->whereIn('status', [OrderItemStatus::Pending, OrderItemStatus::Preparing]))
            ->count();

        return now()->addMinutes($longestPrep + $queueAhead * $avgMinutes);
    }

    /** staff nudge a live ETA. Refused if this station has no ETA to adjust. */
    public function adjust(Order $order, Destination $destination, int $minutes, Staff $actor): Order
    {
        $column = $destination === Destination::Kitchen ? 'kitchen_eta_at' : 'bar_eta_at';

        if ($order->{$column} === null) {
            throw ValidationException::withMessages([
                'order' => 'This order has no station ETA to adjust.',
            ]);
        }

        $order->forceFill([$column => $order->{$column}->copy()->addMinutes($minutes)])->save();

        $this->auditLogger->log($actor, 'eta_adjusted', $order, "{$destination->value}: {$minutes} min");

        $order->refresh();

        event(new OrderLinesUpdated($order, $destination));

        return $order;
    }
}
