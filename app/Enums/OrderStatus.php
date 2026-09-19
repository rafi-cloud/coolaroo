<?php

namespace App\Enums;

use App\Enums\Concerns\HasTransitions;

enum OrderStatus: string
{
    use HasTransitions;

    case PendingPayment = 'pending_payment';
    case Paid = 'paid';
    case Preparing = 'preparing';
    case Ready = 'ready';
    case Served = 'served';
    case Cancelled = 'cancelled';

    public function transitions(): array
    {
        return match ($this) {
            self::PendingPayment => [self::Paid, self::Cancelled],
            self::Paid => [self::Preparing, self::Cancelled],
            self::Preparing => [self::Ready],
            self::Ready => [self::Served],
            self::Served, self::Cancelled => [],
        };
    }
}
