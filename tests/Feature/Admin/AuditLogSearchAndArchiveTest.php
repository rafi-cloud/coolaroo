<?php

namespace Tests\Feature\Admin;

use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\HistoricalDataManagement;
use App\Models\Role;
use App\Models\Staff;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogSearchAndArchiveTest extends TestCase
{
    use RefreshDatabase;

    private Staff $admin;

    private Staff $waitstaff;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RoleSeeder::class, SettingSeeder::class]);

        $adminRole = Role::where('role_name', 'admin')->first();
        $waitstaffRole = Role::where('role_name', 'waitstaff')->first();

        $this->admin = Staff::factory()->create([
            'role_id' => $adminRole->role_id,
            'full_name' => 'Audit Admin Manager',
            'is_active' => true,
        ]);

        $this->waitstaff = Staff::factory()->create([
            'role_id' => $waitstaffRole->role_id,
            'full_name' => 'Floor Waitstaff',
            'is_active' => true,
        ]);

        $this->customer = Customer::factory()->create([
            'full_name' => 'Bob Diner',
            'email' => 'bob@example.com',
        ]);
    }

    public function test_guest_is_redirected_to_staff_login(): void
    {
        $this->get(route('admin.audit-log.index'))->assertRedirect(route('staff.login'));
        $this->get(route('admin.archive.index'))->assertRedirect(route('staff.login'));
    }

    public function test_waitstaff_is_forbidden_from_audit_log_and_archive(): void
    {
        $this->actingAs($this->waitstaff, 'staff')
            ->get(route('admin.audit-log.index'))
            ->assertForbidden();

        $this->actingAs($this->waitstaff, 'staff')
            ->get(route('admin.archive.index'))
            ->assertForbidden();
    }

    public function test_admin_can_view_audit_log_page(): void
    {
        AuditLog::create([
            'staff_id' => $this->admin->staff_id,
            'action_type' => 'setting_update',
            'entity_name' => 'setting',
            'entity_id' => null,
            'details' => [
                'reason' => 'Adjusted closing time for weekend',
                'before' => ['closing_time' => '23:00'],
                'after' => ['closing_time' => '00:00'],
            ],
            'ip_address' => '192.168.1.100',
            'logged_at' => now(),
        ]);

        $response = $this->actingAs($this->admin, 'staff')
            ->get(route('admin.audit-log.index'));

        $response->assertOk();
        $response->assertSee('Audit Log');
        $response->assertSee('Setting Update');
        $response->assertSee('Adjusted closing time for weekend');
        $response->assertSee('192.168.1.100');
        $response->assertSee($this->admin->full_name);
        $response->assertSee('data-testid="admin-audit-table"', false);
    }

    public function test_admin_can_filter_audit_logs(): void
    {
        AuditLog::create([
            'staff_id' => $this->admin->staff_id,
            'action_type' => 'menu_update',
            'entity_name' => 'menu_item',
            'entity_id' => 12,
            'details' => ['reason' => 'Price increase on Steak'],
            'ip_address' => '10.0.0.1',
            'logged_at' => now()->subDay(),
        ]);

        AuditLog::create([
            'staff_id' => $this->admin->staff_id,
            'action_type' => 'feedback_hide',
            'entity_name' => 'feedback',
            'entity_id' => 45,
            'details' => ['reason' => 'Profanity in review'],
            'ip_address' => '10.0.0.2',
            'logged_at' => now(),
        ]);

        $resAction = $this->actingAs($this->admin, 'staff')
            ->get(route('admin.audit-log.index', ['action_type' => 'feedback_hide']));

        $resAction->assertOk();
        $resAction->assertSee('Profanity in review');
        $resAction->assertDontSee('Price increase on Steak');

        $resEntity = $this->actingAs($this->admin, 'staff')
            ->get(route('admin.audit-log.index', ['entity_name' => 'menu_item']));

        $resEntity->assertOk();
        $resEntity->assertSee('Price increase on Steak');
        $resEntity->assertDontSee('Profanity in review');

        $resSearch = $this->actingAs($this->admin, 'staff')
            ->get(route('admin.audit-log.index', ['search' => 'Steak']));

        $resSearch->assertOk();
        $resSearch->assertSee('Price increase on Steak');
        $resSearch->assertDontSee('Profanity in review');
    }

    public function test_admin_can_view_archived_records_tab(): void
    {
        $log = AuditLog::create([
            'staff_id' => $this->admin->staff_id,
            'action_type' => 'menu_item_archive',
            'entity_name' => 'menu_item',
            'entity_id' => 99,
            'details' => ['reason' => 'Discontinued seasonal cocktail'],
            'logged_at' => now(),
        ]);

        HistoricalDataManagement::create([
            'log_id' => $log->log_id,
            'entity_name' => 'menu_item',
            'record_id' => '99',
            'record_data' => [
                'item_id' => 99,
                'name' => 'Summer Passion Cocktail',
                'price' => '18.50',
                'is_active' => false,
            ],
            'status' => 'archived',
            'archived_at' => now(),
        ]);

        $response = $this->actingAs($this->admin, 'staff')
            ->get(route('admin.archive.index'));

        $response->assertOk();
        $response->assertSee('Historical Data Archives');
        $response->assertSee('Menu Item');
        $response->assertSee('99');
        $response->assertSee('Summer Passion Cocktail');
        $response->assertSee('Discontinued seasonal cocktail');
        $response->assertSee('data-testid="admin-archive-table"', false);
    }

    public function test_admin_can_filter_archived_records(): void
    {
        $log1 = AuditLog::create([
            'staff_id' => $this->admin->staff_id,
            'action_type' => 'menu_item_archive',
            'entity_name' => 'menu_item',
            'entity_id' => 101,
            'logged_at' => now(),
        ]);

        HistoricalDataManagement::create([
            'log_id' => $log1->log_id,
            'entity_name' => 'menu_item',
            'record_id' => '101',
            'record_data' => ['name' => 'Classic Margherita'],
            'status' => 'archived',
            'archived_at' => now(),
        ]);

        $log2 = AuditLog::create([
            'staff_id' => $this->admin->staff_id,
            'action_type' => 'staff_deactivate',
            'entity_name' => 'staff',
            'entity_id' => 202,
            'logged_at' => now(),
        ]);

        HistoricalDataManagement::create([
            'log_id' => $log2->log_id,
            'entity_name' => 'staff',
            'record_id' => '202',
            'record_data' => ['full_name' => 'Former Employee'],
            'status' => 'archived',
            'archived_at' => now(),
        ]);

        $resEntity = $this->actingAs($this->admin, 'staff')
            ->get(route('admin.archive.index', ['entity_name' => 'staff']));

        $resEntity->assertOk();
        $resEntity->assertSee('Former Employee');
        $resEntity->assertDontSee('Classic Margherita');

        $resSearch = $this->actingAs($this->admin, 'staff')
            ->get(route('admin.archive.index', ['search' => 'Margherita']));

        $resSearch->assertOk();
        $resSearch->assertSee('Classic Margherita');
        $resSearch->assertDontSee('Former Employee');
    }
}
