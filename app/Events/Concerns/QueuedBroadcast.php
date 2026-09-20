<?php

namespace App\Events\Concerns;

/**
 * NFR10, 07.8, T222. Retry settings every broadcast event shares.
 *
 * `Illuminate\Broadcasting\BroadcastEvent` copies `tries`, `backoff` and
 * `timeout` off the event when it queues it, so declaring them here reaches
 * the worker without a job class of our own.
 *
 * The numbers are deliberately shorter than the mail queue's (60/300/900 in
 * QueuedMailable): a floor or station update is worth retrying for seconds,
 * not for a quarter of an hour, because by then the screen has moved on and a
 * stale frame is worse than a missing one. Three attempts over ~20 seconds
 * covers a Reverb restart; past that the failed job is the record, and the
 * screens recover on their next poll.
 */
trait QueuedBroadcast
{
    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [5, 15];

    public int $timeout = 10;

    public function broadcastQueue(): string
    {
        return 'broadcasts';
    }
}
