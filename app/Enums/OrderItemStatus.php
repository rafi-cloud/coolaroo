<?php

namespace App\Enums;

use App\Enums\Concerns\HasTransitions;

enum OrderItemStatus: string
{
    use HasTransitions;

    case Pending = 'pending';
    case Preparing = 'preparing';
    case Ready = 'ready';
    case Served = 'served';
    case Cancelled = 'cancelled';

    public function transitions(): array
    {
        return match ($this) {
            self::Pending => [self::Preparing, self::Cancelled],
            self::Preparing => [self::Ready, self::Cancelled],
            self::Ready => [self::Served],
            self::Served, self::Cancelled => [],
        };
    }
}
