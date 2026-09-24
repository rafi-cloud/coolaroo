<?php

namespace Tests\Feature\Admin;

use App\Models\AuditLog;
use App\Models\HistoricalDataManagement;
use App\Models\Role;
use App\Models\Staff;
use App\Services\StaffAccountService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class StaffAccountTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): Staff
    {
        $role = Role::factory()->admin()->create();

        return Staff::factory()->create(['role_id' => $role->role_id]);
    }

    public function test_an_admin_can_create_a_staff_account(): void
    {
        $admin = $this->admin();
        $role = Role::factory()->waitstaff()->create();

        $response = $this->actingAs($admin, 'staff')->post('/admin/staff', [
            'full_name' => 'New Waiter',
            'email' => 'new-waiter@coolaroo.test',
            'phone' => '0412345678',
            'role_id' => $role->role_id,
            'password' => 'Temp@12345',
            'password_confirmation' => 'Temp@12345',
        ]);

        $response->assertRedirect('/admin/staff');
        $this->assertDatabaseHas('staff', ['email' => 'new-waiter@coolaroo.test']);
    }

    public function test_a_non_admin_cannot_reach_the_staff_list(): void
    {
        $role = Role::factory()->waitstaff()->create();
        $waiter = Staff::factory()->create(['role_id' => $role->role_id]);

        $this->actingAs($waiter, 'staff')->get('/admin/staff')->assertForbidden();
    }

    public function test_deactivating_a_staff_member_writes_an_audit_log_and_a_snapshot(): void
    {
        $admin = $this->admin();
        $role = Role::factory()->waitstaff()->create();
        $staff = Staff::factory()->create(['role_id' => $role->role_id]);

        $this->actingAs($admin, 'staff')->patch("/admin/staff/{$staff->staff_id}/deactivate", [
            'reason' => 'No longer employed',
        ]);

        $this->assertFalse($staff->fresh()->is_active);

        $log = AuditLog::where('entity_name', 'staff')->where('entity_id', $staff->staff_id)->first();
        $this->assertNotNull($log);
        $this->assertSame('staff_deactivate', $log->action_type);
        $this->assertSame($admin->staff_id, $log->staff_id);
        $this->assertSame('No longer employed', $log->details['reason']);

        $snapshot = HistoricalDataManagement::where('log_id', $log->log_id)->first();
        $this->assertNotNull($snapshot);
        $this->assertSame($staff->full_name, $snapshot->record_data['full_name']);
        $this->assertArrayNotHasKey('password_hash', $snapshot->record_data);
    }

    public function test_an_admin_cannot_deactivate_their_own_account(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin, 'staff')->patch("/admin/staff/{$admin->staff_id}/deactivate");

        $response->assertSessionHasErrors('staff');
        $this->assertTrue($admin->fresh()->is_active);
    }

    public function test_the_last_active_admin_cannot_be_deactivated(): void
    {
        $adminRole = Role::factory()->admin()->create();
        $onlyAdmin = Staff::factory()->create(['role_id' => $adminRole->role_id]);
        $waitstaffRole = Role::factory()->waitstaff()->create();
        $actor = Staff::factory()->create(['role_id' => $waitstaffRole->role_id]);

        $this->expectException(ValidationException::class);

        app(StaffAccountService::class)->deactivate($onlyAdmin, $actor);
    }

    public function test_an_admin_can_be_deactivated_if_another_active_admin_remains(): void
    {
        $adminRole = Role::factory()->admin()->create();
        $admin = Staff::factory()->create(['role_id' => $adminRole->role_id]);
        $anotherAdmin = Staff::factory()->create(['role_id' => $adminRole->role_id]);

        $this->actingAs($admin, 'staff')->patch("/admin/staff/{$anotherAdmin->staff_id}/deactivate");

        $this->assertFalse($anotherAdmin->fresh()->is_active);
    }

    public function test_a_deactivated_staff_member_is_logged_out_on_their_next_request(): void
    {
        Route::middleware(['web', 'auth:staff', 'staff.session'])->get('/__test/staff-area', fn () => 'ok');

        $admin = $this->admin();
        $role = Role::factory()->waitstaff()->create();
        $staff = Staff::factory()->create(['role_id' => $role->role_id]);

        $this->actingAs($staff, 'staff')->get('/__test/staff-area')->assertOk();

        $this->actingAs($admin, 'staff')->patch("/admin/staff/{$staff->staff_id}/deactivate");

        $response = $this->actingAs($staff->fresh(), 'staff')->get('/__test/staff-area');

        $response->assertRedirect('/staff/login');
        $this->assertGuest('staff');
    }

    public function test_reactivating_restores_access(): void
    {
        $admin = $this->admin();
        $role = Role::factory()->waitstaff()->create();
        $staff = Staff::factory()->create(['role_id' => $role->role_id, 'is_active' => false]);

        $this->actingAs($admin, 'staff')->patch("/admin/staff/{$staff->staff_id}/reactivate");

        $this->assertTrue($staff->fresh()->is_active);
    }

    public function test_editing_a_staff_member_does_not_touch_their_password(): void
    {
        $admin = $this->admin();
        $role = Role::factory()->waitstaff()->create();
        $staff = Staff::factory()->create(['role_id' => $role->role_id, 'password_hash' => 'original-password']);
        $originalHash = $staff->password_hash;

        $this->actingAs($admin, 'staff')->patch("/admin/staff/{$staff->staff_id}", [
            'full_name' => 'Renamed',
            'email' => $staff->email,
            'phone' => $staff->phone,
            'role_id' => $role->role_id,
        ]);

        $this->assertSame($originalHash, $staff->fresh()->password_hash);
        $this->assertSame('Renamed', $staff->fresh()->full_name);
    }
}
