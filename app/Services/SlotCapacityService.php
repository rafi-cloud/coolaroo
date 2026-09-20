<?php

namespace App\Services;

use App\Models\SlotCapacity;
use App\Models\Staff;
use Illuminate\Validation\ValidationException;

/**
 * FR100.
 */
class SlotCapacityService
{
    public function __construct(private AuditLogger $auditLogger) {}

    public function create(array $data, Staff $actor): SlotCapacity
    {
        $slot = SlotCapacity::create($data);

        $this->auditLogger->log($actor, 'slot_create', $slot);

        return $slot;
    }

    public function update(SlotCapacity $slot, array $data, Staff $actor): SlotCapacity
    {
        $slot->update($data);

        $this->auditLogger->log($actor, 'slot_update', $slot);

        return $slot;
    }

    public function delete(SlotCapacity $slot, Staff $actor): void
    {
        if ($slot->isInUse()) {
            throw ValidationException::withMessages([
                'slot' => 'Cannot delete a time slot that has reservations.',
            ]);
        }

        $this->auditLogger->log($actor, 'slot_delete', $slot);

        $slot->delete();
    }
}
