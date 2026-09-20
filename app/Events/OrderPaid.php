<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use App\Events\Concerns\QueuedBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/** FR55, 07.8. New paid lines for the station displays and admin dashboard. */
class OrderPaid implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, QueuedBroadcast, SerializesModels;

    public function __construct(public Order $order)
    {
    }

    /** @return array<int, PrivateChannel> */
    public function broadcastOn(): array
    {
        $channels = [new PrivateChannel('admin')];

        foreach ($this->order->items->pluck('destination')->unique() as $destination) {
            $channels[] = new PrivateChannel("station.{$destination->value}");
        }

        return $channels;
    }

    public function broadcastWith(): array
    {
        return ['order_id' => $this->order->order_id, 'order_number' => $this->order->order_number];
    }
}
