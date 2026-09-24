<?php

namespace App\Events;

use App\Events\Concerns\QueuedBroadcast;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Carries the key and value rather than the Setting
 * model: this payload reaches unauthenticated visitors on the public menu
 * channel, so only the switched key travels.
 */
class SettingSwitched implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, QueuedBroadcast, SerializesModels;

    public function __construct(public string $key, public string $value) {}

    /** @return array<int, Channel|PrivateChannel> */
    public function broadcastOn(): array
    {
        return [new Channel('menu'), new PrivateChannel('floor')];
    }

    public function broadcastWith(): array
    {
        return ['key' => $this->key, 'value' => $this->value];
    }
}
