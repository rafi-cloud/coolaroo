<?php

namespace App\Policies;

use App\Enums\Destination;
use App\Models\MenuItem;
use App\Models\Staff;

/**
 * 3.3: menu rows. Admin passes every method via Gate::before.
 */
class MenuItemPolicy
{
    public function manage(Staff $staff): bool
    {
        return false;
    }

    public function toggleAvailability(Staff $staff, MenuItem $menuItem): bool
    {
        return match ($staff->role->role_name) {
            'kitchen' => $menuItem->destination === Destination::Kitchen,
            'bar' => $menuItem->destination === Destination::Bar,
            default => false,
        };
    }
}
