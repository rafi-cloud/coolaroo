<?php

namespace Tests\Feature\Auth;

use App\Models\MenuItem;
use App\Models\Role;
use App\Models\Staff;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class AdminGateBypassTest extends TestCase
{
    private function staff(string $roleName): Staff
    {
        $staff = new Staff();
        $staff->setRelation('role', (new Role())->forceFill(['role_name' => $roleName]));

        return $staff;
    }

    public function test_admin_passes_a_policy_check_their_role_would_otherwise_fail(): void
    {
        $this->assertTrue(Gate::forUser($this->staff('admin'))->allows('manage', MenuItem::class));
    }

    public function test_non_admin_still_gets_the_policys_real_answer(): void
    {
        $this->assertFalse(Gate::forUser($this->staff('waitstaff'))->allows('manage', MenuItem::class));
    }
}
