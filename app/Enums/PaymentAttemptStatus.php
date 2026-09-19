<?php

namespace App\Enums;

use App\Enums\Concerns\HasTransitions;

enum PaymentAttemptStatus: string
{
    use HasTransitions;

    case Pending = 'pending';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Expired = 'expired';

    public function transitions(): array
    {
        return match ($this) {
            self::Pending => [self::Succeeded, self::Failed, self::Expired],
            self::Succeeded, self::Failed, self::Expired => [],
        };
    }
}
