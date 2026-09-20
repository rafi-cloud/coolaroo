<?php

namespace App\Services;

use App\Enums\Destination;
use App\Enums\OrderItemStatus;
use App\Events\OrderLinesUpdated;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Setting;
use App\Models\Staff;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/** BR30. Calculation (T071's original formula, relocated here) and staff adjustment (FR59). */
class EtaService
{
    public function __construct(private AuditLogger $auditLogger) {}

    /** BR30: longest prep time at the station + orders ahead x avg minutes per order. */
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

        $queueAhead = OrderItem::where('destination', $destination->value)
            ->whereIn('status', [OrderItemStatus::Pending, OrderItemStatus::Preparing])
            ->where('order_id', '!=', $order->order_id)
            ->distinct('order_id')
            ->count('order_id');

        return now()->addMinutes($longestPrep + $queueAhead * $avgMinutes);
    }

    /** FR59: staff nudge a live ETA. Refused if this station has no ETA to adjust. */
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
