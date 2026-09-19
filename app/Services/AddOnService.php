<?php

namespace App\Services;

use App\Models\AddOnGroup;
use App\Models\AddOnOption;
use App\Models\MenuItem;

/**
 * FR28. Groups/options are tightly coupled and each method is a thin
 * passthrough — one service, not one per entity (see T043's guide).
 */
class AddOnService
{
    public function createGroup(MenuItem $item, array $data): AddOnGroup
    {
        return $item->addOnGroups()->create($data);
    }

    public function updateGroup(AddOnGroup $group, array $data): AddOnGroup
    {
        $group->update($data);

        return $group;
    }

    public function deleteGroup(AddOnGroup $group): void
    {
        $group->delete();
    }

    public function createOption(AddOnGroup $group, array $data): AddOnOption
    {
        return $group->options()->create($data);
    }

    public function updateOption(AddOnOption $option, array $data): AddOnOption
    {
        $option->update($data);

        return $option;
    }

    public function deleteOption(AddOnOption $option): void
    {
        $option->delete();
    }
}
