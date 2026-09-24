<?php

namespace App\Events;

use App\Events\Concerns\QueuedBroadcast;
use App\Models\Refund;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/** Admin's refund queue. */
class RefundRequested implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, QueuedBroadcast, SerializesModels;

    public function __construct(public Refund $refund) {}

    /** @return array<int, PrivateChannel> */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('admin')];
    }

    public function broadcastWith(): array
    {
        return [
            'refund_id' => $this->refund->refund_id,
            'order_id' => $this->refund->order_id,
            'amount' => (string) $this->refund->amount,
        ];
    }
}
