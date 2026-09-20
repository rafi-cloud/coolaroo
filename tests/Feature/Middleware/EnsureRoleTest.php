<?php

namespace Tests\Feature\Middleware;

use App\Models\Role;
use App\Models\Staff;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class EnsureRoleTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['role:waitstaff'])->get('/__test/role-gate', fn () => 'ok');
    }

    private function staffWithRole(string $roleName): Staff
    {
        $staff = new Staff;
        $staff->setRelation('role', (new Role)->forceFill(['role_name' => $roleName]));

        return $staff;
    }

    public function test_guest_is_forbidden(): void
    {
        $this->get('/__test/role-gate')->assertForbidden();
    }

    public function test_matching_role_is_allowed(): void
    {
        $this->actingAs($this->staffWithRole('waitstaff'), 'staff')
            ->get('/__test/role-gate')
            ->assertOk();
    }

    public function test_non_matching_role_is_forbidden(): void
    {
        $this->actingAs($this->staffWithRole('kitchen'), 'staff')
            ->get('/__test/role-gate')
            ->assertForbidden();
    }

    public function test_admin_bypasses_the_listed_roles(): void
    {
        $this->actingAs($this->staffWithRole('admin'), 'staff')
            ->get('/__test/role-gate')
            ->assertOk();
    }
}
