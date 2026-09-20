<?php

namespace App\Events;

use App\Events\Concerns\QueuedBroadcast;
use App\Models\Order;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/** FR38, 07.8. Customer order timeline; floor's ready-to-serve alerts. */
class OrderStatusChanged implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, QueuedBroadcast, SerializesModels;

    public function __construct(public Order $order) {}

    /** @return array<int, PrivateChannel> */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("order.{$this->order->order_id}"),
            new PrivateChannel('floor'),
        ];
    }

    public function broadcastWith(): array
    {
        return ['order_id' => $this->order->order_id, 'status' => $this->order->status->value];
    }
}
