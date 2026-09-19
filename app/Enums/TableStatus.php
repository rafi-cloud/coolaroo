<?php

namespace App\Enums;

use App\Enums\Concerns\HasTransitions;

enum TableStatus: string
{
    use HasTransitions;

    case Available = 'available';
    case Reserved = 'reserved';
    case Occupied = 'occupied';

    public function transitions(): array
    {
        return match ($this) {
            self::Available => [self::Occupied, self::Reserved],
            self::Reserved => [self::Occupied, self::Available],
            self::Occupied => [self::Available],
        };
    }
}
