<?php

namespace Tests\Feature\Staff;

use App\Enums\TableStatus;
use App\Enums\VisitCloseReason;
use App\Models\RestaurantTable;
use App\Models\Role;
use App\Models\Staff;
use App\Models\Visit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TableSeatClearTest extends TestCase
{
    use RefreshDatabase;

    private function staffWithRole(string $role): Staff
    {
        $roleModel = Role::firstWhere('role_name', $role) ?? Role::factory()->{$role}()->create();

        return Staff::factory()->create(['role_id' => $roleModel->role_id]);
    }

    public function test_waitstaff_seat_a_walk_in(): void
    {
        $table = RestaurantTable::factory()->create(['status' => TableStatus::Available]);

        $this->actingAs($this->staffWithRole('waitstaff'), 'staff')
            ->post(route('staff.tables.seat', $table), ['guest_count' => 3])
            ->assertRedirect();

        $this->assertSame(TableStatus::Occupied, $table->fresh()->status);
    }

    public function test_seating_an_occupied_table_is_refused_on_the_floor(): void
    {
        $table = RestaurantTable::factory()->create(['status' => TableStatus::Occupied]);

        $this->actingAs($this->staffWithRole('waitstaff'), 'staff')
            ->from(route('staff.floor.index'))
            ->post(route('staff.tables.seat', $table), ['guest_count' => 2])
            ->assertRedirect(route('staff.floor.index'))
            ->assertSessionHasErrors('table');
    }

    public function test_waitstaff_clear_a_table(): void
    {
        $table = RestaurantTable::factory()->create(['status' => TableStatus::Occupied]);

        $this->actingAs($this->staffWithRole('waitstaff'), 'staff')
            ->post(route('staff.tables.clear'), ['table_ids' => [$table->table_id]])
            ->assertRedirect();

        $this->assertSame(TableStatus::Available, $table->fresh()->status);
    }

    public function test_clearing_a_seated_table_closes_its_open_visit(): void
    {
        $table = RestaurantTable::factory()->create(['status' => TableStatus::Available]);
        $waiter = $this->staffWithRole('waitstaff');

        $this->actingAs($waiter, 'staff')->post(route('staff.tables.seat', $table), ['guest_count' => 2]);

        $this->actingAs($waiter, 'staff')
            ->post(route('staff.tables.clear'), ['table_ids' => [$table->table_id]])
            ->assertRedirect();

        $visit = Visit::where('table_id', $table->table_id)->latest('visit_id')->first();

        $this->assertSame(TableStatus::Available, $table->fresh()->status);
        $this->assertNotNull($visit->closed_at);
        $this->assertSame(VisitCloseReason::StaffClear, $visit->close_reason);
    }

    public function test_clearing_a_table_that_is_already_available_reports_it_on_the_floor(): void
    {
        $table = RestaurantTable::factory()->create(['status' => TableStatus::Available]);

        $this->actingAs($this->staffWithRole('waitstaff'), 'staff')
            ->from(route('staff.floor.index'))
            ->post(route('staff.tables.clear'), ['table_ids' => [$table->table_id]])
            ->assertRedirect(route('staff.floor.index'))
            ->assertSessionHasErrors('table');
    }

    public function test_kitchen_staff_cannot_seat_a_table(): void
    {
        $table = RestaurantTable::factory()->create(['status' => TableStatus::Available]);

        $this->actingAs($this->staffWithRole('kitchen'), 'staff')
            ->post(route('staff.tables.seat', $table), [])
            ->assertForbidden();
    }
}
