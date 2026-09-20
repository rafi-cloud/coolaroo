<?php

namespace App\Events;

use App\Enums\Destination;
use App\Events\Concerns\QueuedBroadcast;
use App\Models\Order;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/** FR58, FR59, 07.8. Line status or ETA moved at one station (T081, T082). */
class OrderLinesUpdated implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, QueuedBroadcast, SerializesModels;

    public function __construct(public Order $order, public Destination $destination) {}

    /** @return array<int, PrivateChannel> */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("station.{$this->destination->value}"),
            new PrivateChannel("order.{$this->order->order_id}"),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'order_id' => $this->order->order_id,
            'order_number' => $this->order->order_number,
            'destination' => $this->destination->value,
            'status' => $this->order->status->value,
            'eta_at' => $this->destination === Destination::Kitchen
                ? $this->order->kitchen_eta_at?->toIso8601String()
                : $this->order->bar_eta_at?->toIso8601String(),
        ];
    }
}
