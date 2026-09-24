<?php

namespace App\Events\Concerns;

trait QueuedBroadcast
{
    public int $tries = 3;

    /**
     * @var array<int, int>
     */
    public array $backoff = [5, 15];

    public int $timeout = 10;

    public function broadcastQueue(): string
    {
        return 'broadcasts';
    }
}
