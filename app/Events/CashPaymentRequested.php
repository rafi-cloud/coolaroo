<?php

namespace App\Events;

use App\Events\Concerns\QueuedBroadcast;
use App\Models\Order;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/** 07.8, FR48. Floor's cash-waiting list. */
class CashPaymentRequested implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, QueuedBroadcast, SerializesModels;

    public function __construct(public Order $order) {}

    /** @return array<int, PrivateChannel> */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('floor')];
    }

    public function broadcastWith(): array
    {
        return [
            'order_id' => $this->order->order_id,
            'table_id' => $this->order->table_id,
            'order_number' => $this->order->order_number,
        ];
    }
}
