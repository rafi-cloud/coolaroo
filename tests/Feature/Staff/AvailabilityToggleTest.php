<?php

namespace Tests\Feature\Staff;

use App\Enums\Destination;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Role;
use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AvailabilityToggleTest extends TestCase
{
    use RefreshDatabase;

    private function staffWithRole(string $role): Staff
    {
        $roleModel = Role::firstWhere('role_name', $role) ?? Role::factory()->{$role}()->create();

        return Staff::factory()->create(['role_id' => $roleModel->role_id]);
    }

    public function test_kitchen_staff_toggle_their_own_stations_item(): void
    {
        $item = MenuItem::factory()->create(['category_id' => MenuCategory::factory(), 'destination' => Destination::Kitchen]);

        $this->actingAs($this->staffWithRole('kitchen'), 'staff')
            ->patch(route('staff.menu-items.availability', $item))
            ->assertRedirect();

        $this->assertFalse($item->fresh()->is_available);
    }

    /** BR14: the rule this whole task exists for. */
    public function test_kitchen_staff_cannot_toggle_a_bar_items_availability(): void
    {
        $item = MenuItem::factory()->create(['category_id' => MenuCategory::factory(), 'destination' => Destination::Bar]);

        $this->actingAs($this->staffWithRole('kitchen'), 'staff')
            ->patch(route('staff.menu-items.availability', $item))
            ->assertForbidden();

        $this->assertTrue($item->fresh()->is_available);
    }

    public function test_bar_staff_toggle_their_own_add_on_option(): void
    {
        $item = MenuItem::factory()->create(['category_id' => MenuCategory::factory(), 'destination' => Destination::Bar]);
        $option = $item->addOnGroups()->create(['group_name' => 'Add-ons', 'is_required' => false])
            ->options()->create(['option_name' => 'Double', 'price_delta' => 2]);

        $this->actingAs($this->staffWithRole('bar'), 'staff')
            ->patch(route('staff.add-on-options.availability', $option))
            ->assertRedirect();

        $this->assertFalse($option->fresh()->is_available);
    }

    public function test_waitstaff_cannot_reach_the_availability_routes(): void
    {
        $item = MenuItem::factory()->create(['category_id' => MenuCategory::factory(), 'destination' => Destination::Kitchen]);

        $this->actingAs($this->staffWithRole('waitstaff'), 'staff')
            ->patch(route('staff.menu-items.availability', $item))
            ->assertForbidden();
    }
}
