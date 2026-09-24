<?php

namespace App\Policies;

use App\Enums\Destination;
use App\Models\AddOnOption;
use App\Models\Staff;

class AddOnOptionPolicy
{
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
