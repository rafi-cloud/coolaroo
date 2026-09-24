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

/** Alerts the affected station and admin to resolve later. */
class StockConflictDetected implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, QueuedBroadcast, SerializesModels;

    public function __construct(public Order $order, public Destination $destination) {}

    /** @return array<int, PrivateChannel> */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("station.{$this->destination->value}"),
            new PrivateChannel('admin'),
        ];
    }

    public function broadcastWith(): array
    {
        return ['order_id' => $this->order->order_id, 'order_number' => $this->order->order_number];
    }
}
