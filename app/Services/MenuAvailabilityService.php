<?php

namespace App\Services;

use App\Events\MenuAvailabilityChanged;
use App\Models\AddOnOption;
use App\Models\MenuItem;
use App\Models\Staff;

/**
 * FR29, BR14, 07.8. Station-scoped sold-out toggle — deliberately not
 * MenuItemService's job (see that class's own docblock).
 */
class MenuAvailabilityService
{
    public function __construct(private AuditLogger $auditLogger)
    {
    }

    public function toggleMenuItem(MenuItem $item, Staff $actor): MenuItem
    {
        $item->update(['is_available' => ! $item->is_available]);

        $this->auditLogger->log($actor, 'menu_item_availability_toggled', $item);

        event(new MenuAvailabilityChanged($item->refresh()));

        return $item;
    }

    public function toggleAddOnOption(AddOnOption $option, Staff $actor): AddOnOption
    {
        $option->update(['is_available' => ! $option->is_available]);

        $this->auditLogger->log($actor, 'add_on_option_availability_toggled', $option);

        event(new MenuAvailabilityChanged($option->refresh()));

        return $option;
    }
}
