<?php

namespace App\Enums;

use App\Enums\Concerns\HasTransitions;

enum PaymentStatus: string
{
    use HasTransitions;

    case Unpaid = 'unpaid';
    case Paid = 'paid';
    case PartiallyRefunded = 'partially_refunded';
    case Refunded = 'refunded';

    public function transitions(): array
    {
        return match ($this) {
            self::Unpaid => [self::Paid],
            self::Paid => [self::PartiallyRefunded, self::Refunded],
            self::PartiallyRefunded => [self::PartiallyRefunded, self::Refunded],
            self::Refunded => [],
        };
    }
}
