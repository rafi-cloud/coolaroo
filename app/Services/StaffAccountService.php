<?php

namespace App\Services;

use App\Models\Staff;
use Illuminate\Validation\ValidationException;

/**
 * FR02, FR08, BR60, BR62.
 */
class StaffAccountService
{
    public function __construct(private AuditLogger $auditLogger) {}

    public function create(array $data): Staff
    {
        $staff = Staff::create($data);

        $this->auditLogger->log(null, 'staff_create', $staff);

        return $staff;
    }

    public function update(Staff $staff, array $data): Staff
    {
        $staff->update($data);

        $this->auditLogger->log(null, 'staff_update', $staff);

        return $staff;
    }

    public function deactivate(Staff $staff, Staff $actor, ?string $reason = null): void
    {
        if ($staff->staff_id === $actor->staff_id) {
            throw ValidationException::withMessages([
                'staff' => 'You cannot deactivate your own account.',
            ]);
        }

        if ($this->isLastActiveAdmin($staff)) {
            throw ValidationException::withMessages([
                'staff' => 'Cannot deactivate the last active admin.',
            ]);
        }

        $staff->update(['is_active' => false]);

        $this->auditLogger->snapshot($actor, 'staff_deactivate', $staff, $reason);
    }

    public function reactivate(Staff $staff, Staff $actor): void
    {
        $staff->update(['is_active' => true]);

        $this->auditLogger->log($actor, 'staff_reactivate', $staff);
    }

    private function isLastActiveAdmin(Staff $staff): bool
    {
        if ($staff->role->role_name !== 'admin') {
            return false;
        }

        return ! Staff::where('role_id', $staff->role_id)
            ->where('is_active', true)
            ->where('staff_id', '!=', $staff->staff_id)
            ->exists();
    }
}
