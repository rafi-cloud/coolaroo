<?php

namespace App\Enums\Concerns;

use App\Exceptions\InvalidTransitionException;

trait HasTransitions
{
    abstract public function transitions(): array;

    public function canTransitionTo(self $to): bool
    {
        return in_array($to, $this->transitions(), true);
    }

    public function ensureCanTransitionTo(self $to): void
    {
        if (! $this->canTransitionTo($to)) {
            throw new InvalidTransitionException($this, $to);
        }
    }
}
