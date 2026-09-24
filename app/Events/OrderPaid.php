<?php

namespace App\Events;

use App\Events\Concerns\QueuedBroadcast;
use App\Models\Order;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/** New paid lines for the station displays and admin dashboard. */
class OrderPaid implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, QueuedBroadcast, SerializesModels;

    public function __construct(public Order $order) {}

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
