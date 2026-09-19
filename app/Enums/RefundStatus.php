<?php

namespace App\Enums;

use App\Enums\Concerns\HasTransitions;

enum RefundStatus: string
{
    use HasTransitions;

    case Requested = 'requested';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';
    case Rejected = 'rejected';

    public function transitions(): array
    {
        return match ($this) {
            self::Requested => [self::Processing, self::Completed, self::Rejected],
            self::Processing => [self::Completed, self::Failed],
            self::Failed => [self::Processing],
            self::Completed, self::Rejected => [],
        };
    }
}
