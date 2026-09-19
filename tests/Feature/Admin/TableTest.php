<?php

namespace Tests\Feature\Admin;

use App\Models\AuditLog;
use App\Models\RestaurantTable;
use App\Models\Role;
use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TableTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): Staff
    {
        $role = Role::factory()->admin()->create();

        return Staff::factory()->create(['role_id' => $role->role_id]);
    }

    public function test_an_admin_can_create_a_table_with_a_generated_qr_token(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin, 'staff')->post('/admin/tables', [
            'table_number' => 'T20',
            'seat_capacity' => 4,
            'section' => 'Dining',
        ]);

        $response->assertRedirect('/admin/tables');
        $table = RestaurantTable::where('table_number', 'T20')->firstOrFail();
        $this->assertNotEmpty($table->qr_token);
    }

    public function test_table_numbers_must_be_unique(): void
    {
        $admin = $this->admin();
        RestaurantTable::factory()->create(['table_number' => 'T1']);

        $response = $this->actingAs($admin, 'staff')->post('/admin/tables', [
            'table_number' => 'T1',
            'seat_capacity' => 4,
        ]);

        $response->assertSessionHasErrors('table_number');
    }

    public function test_a_non_admin_cannot_reach_the_table_list(): void
    {
        $role = Role::factory()->waitstaff()->create();
        $waiter = Staff::factory()->create(['role_id' => $role->role_id]);

        $this->actingAs($waiter, 'staff')->get('/admin/tables')->assertForbidden();
    }

    public function test_deactivating_a_table_writes_an_audit_snapshot(): void
    {
        $admin = $this->admin();
        $table = RestaurantTable::factory()->create();

        $this->actingAs($admin, 'staff')->patch("/admin/tables/{$table->table_id}/deactivate");

        $this->assertFalse($table->fresh()->is_active);
        $this->assertNotNull(AuditLog::where('entity_name', 'restaurant_table')->where('action_type', 'table_deactivate')->first());
    }

    public function test_status_override_bypasses_the_normal_transition_map_and_requires_a_reason(): void
    {
        $admin = $this->admin();
        $table = RestaurantTable::factory()->create();

        // Occupied -> Reserved is not a legal transition per TableStatus::transitions(),
        // but override doesn't go through that map at all.
        $table->forceFill(['status' => 'occupied'])->save();

        $response = $this->actingAs($admin, 'staff')->patch("/admin/tables/{$table->table_id}/status", [
            'status' => 'reserved',
            'reason' => 'Kitchen closed early, freeing this table for a booking',
        ]);

        $response->assertRedirect();
        $this->assertSame('reserved', $table->fresh()->status->value);
        $this->assertNotNull(AuditLog::where('entity_name', 'restaurant_table')->where('action_type', 'table_status')->first());
    }
}
