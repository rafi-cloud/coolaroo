<?php

namespace App\Enums;

use App\Enums\Concerns\HasTransitions;

enum ReservationStatus: string
{
    use HasTransitions;

    case Requested = 'requested';
    case Confirmed = 'confirmed';
    case Declined = 'declined';
    case Expired = 'expired';
    case Cancelled = 'cancelled';
    case Seated = 'seated';
    case Completed = 'completed';
    case NoShow = 'no_show';

    public function transitions(): array
    {
        return match ($this) {
            self::Requested => [self::Confirmed, self::Declined, self::Expired, self::Cancelled],
            self::Confirmed => [self::Requested, self::Cancelled, self::Seated, self::NoShow],
            self::Seated => [self::Completed],
            self::Declined, self::Expired, self::Cancelled, self::Completed, self::NoShow => [],
        };
    }
}
