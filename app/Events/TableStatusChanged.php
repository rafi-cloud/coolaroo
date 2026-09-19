<?php

namespace App\Events;

use App\Models\RestaurantTable;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/** 07.8. Floor grid and admin dashboard live updates. */
class TableStatusChanged implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(public RestaurantTable $table)
    {
    }

    /** @return array<int, PrivateChannel> */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('floor'), new PrivateChannel('admin')];
    }

    public function broadcastWith(): array
    {
        return ['table_id' => $this->table->table_id, 'status' => $this->table->status->value];
    }
}
