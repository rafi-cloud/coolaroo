<?php

namespace Tests\Feature\Staff;

use App\Enums\TableStatus;
use App\Models\RestaurantTable;
use App\Models\Role;
use App\Models\Staff;
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

    public function test_seating_two_tables_together_occupies_both(): void
    {
        $primary = RestaurantTable::factory()->create(['status' => TableStatus::Available]);
        $other = RestaurantTable::factory()->create(['status' => TableStatus::Available]);

        $this->actingAs($this->staffWithRole('waitstaff'), 'staff')
            ->post(route('staff.tables.seat', $primary), ['other_table_ids' => [$other->table_id]])
            ->assertRedirect();

        $this->assertSame(TableStatus::Occupied, $primary->fresh()->status);
        $this->assertSame(TableStatus::Occupied, $other->fresh()->status);
    }

    public function test_waitstaff_clear_a_table(): void
    {
        $table = RestaurantTable::factory()->create(['status' => TableStatus::Occupied]);

        $this->actingAs($this->staffWithRole('waitstaff'), 'staff')
            ->post(route('staff.tables.clear'), ['table_ids' => [$table->table_id]])
            ->assertRedirect();

        $this->assertSame(TableStatus::Available, $table->fresh()->status);
    }

    public function test_kitchen_staff_cannot_seat_a_table(): void
    {
        $table = RestaurantTable::factory()->create(['status' => TableStatus::Available]);

        $this->actingAs($this->staffWithRole('kitchen'), 'staff')
            ->post(route('staff.tables.seat', $table), [])
            ->assertForbidden();
    }
}
