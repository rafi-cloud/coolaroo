<?php

namespace App\Events;

use App\Models\Reservation;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use App\Events\Concerns\QueuedBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * FR71, 07.8. One event, the alert kinds FR71 lists, plus FR70/BR39's no-show
 * suggestion (T160) — the scheduler's only way of telling the floor a booking
 * is past grace, since only staff may confirm the no-show itself.
 * Constants rather than a backed enum: no column stores these.
 */
class ReservationAlert implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, QueuedBroadcast, SerializesModels;

    public const UNASSIGNED = 'unassigned';

    public const PLACE_SIGN = 'place_sign';

    public const STILL_OCCUPIED = 'still_occupied';

    public const NO_SHOW_SUGGESTED = 'no_show_suggested';

    public function __construct(public Reservation $reservation, public string $kind)
    {
    }

    /** @return array<int, PrivateChannel> */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('floor'), new PrivateChannel('admin')];
    }

    public function broadcastWith(): array
    {
        return [
            'reservation_id' => $this->reservation->reservation_id,
            'kind' => $this->kind,
            'booking_date' => $this->reservation->booking_date?->toDateString(),
            'booking_time' => $this->reservation->booking_time,
            'party_size' => $this->reservation->party_size,
        ];
    }
}
