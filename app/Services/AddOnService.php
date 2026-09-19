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
    public function __construct(private AuditLogger $auditLogger)
    {
    }

    public function createGroup(MenuItem $item, array $data): AddOnGroup
    {
        $group = $item->addOnGroups()->create($data);

        $this->auditLogger->log(null, 'add_on_group_create', $group);

        return $group;
    }

    public function updateGroup(AddOnGroup $group, array $data): AddOnGroup
    {
        $group->update($data);

        $this->auditLogger->log(null, 'add_on_group_update', $group);

        return $group;
    }

    public function deleteGroup(AddOnGroup $group): void
    {
        $this->auditLogger->log(null, 'add_on_group_delete', $group);

        $group->delete();
    }

    public function createOption(AddOnGroup $group, array $data): AddOnOption
    {
        $option = $group->options()->create($data);

        $this->auditLogger->log(null, 'add_on_option_create', $option);

        return $option;
    }

    public function updateOption(AddOnOption $option, array $data): AddOnOption
    {
        $option->update($data);

        $this->auditLogger->log(null, 'add_on_option_update', $option);

        return $option;
    }

    public function deleteOption(AddOnOption $option): void
    {
        $this->auditLogger->log(null, 'add_on_option_delete', $option);

        $option->delete();
    }
}
