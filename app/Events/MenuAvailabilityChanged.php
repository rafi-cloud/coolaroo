<?php

namespace App\Events;

use App\Models\AddOnOption;
use App\Models\MenuItem;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use App\Events\Concerns\QueuedBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * FR29, 07.8. Public 'menu' channel — a plain Channel, not PrivateChannel:
 * routes/channels.php has no 'menu' callback because visitors are not
 * authenticated (T110).
 */
class MenuAvailabilityChanged implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, QueuedBroadcast, SerializesModels;

    public function __construct(public MenuItem|AddOnOption $entity)
    {
    }

    /** @return array<int, Channel> */
    public function broadcastOn(): array
    {
        return [new Channel('menu')];
    }

    public function broadcastWith(): array
    {
        return [
            'entity' => $this->entity instanceof MenuItem ? 'menu_item' : 'add_on_option',
            'id' => $this->entity->getKey(),
            'is_available' => (bool) $this->entity->is_available,
        ];
    }
}
