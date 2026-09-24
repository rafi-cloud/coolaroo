<?php

namespace App\Policies;

use App\Enums\Destination;
use App\Models\AddOnOption;
use App\Models\Staff;

/** 3.3: add-on rows. Admin passes every method via Gate::before. */
class AddOnOptionPolicy
{
    /** station scoping resolves through the option's own item. */
    public function toggleAvailability(Staff $staff, AddOnOption $option): bool
    {
        $destination = $option->group->menuItem->destination;

        return match ($staff->role->role_name) {
            'kitchen' => $destination === Destination::Kitchen,
            'bar' => $destination === Destination::Bar,
            default => false,
        };
    }
}
