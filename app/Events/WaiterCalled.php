<?php

namespace App\Events;

use App\Events\Concerns\QueuedBroadcast;
use App\Models\RestaurantTable;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/** 07.8, FR40. Floor alert — live only, nothing is persisted (BR50). */
class WaiterCalled implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, QueuedBroadcast, SerializesModels;

    public function __construct(public RestaurantTable $table) {}

    /** @return array<int, PrivateChannel> */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('floor')];
    }

    public function broadcastWith(): array
    {
        return [
            'table_id' => $this->table->table_id,
            'table_number' => $this->table->table_number,
            'called_at' => now()->toIso8601String(),
        ];
    }
}
