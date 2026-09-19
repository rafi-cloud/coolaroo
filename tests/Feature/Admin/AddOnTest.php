<?php

namespace Tests\Feature\Admin;

use App\Models\MenuItem;
use App\Models\Role;
use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AddOnTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): Staff
    {
        $role = Role::factory()->admin()->create();

        return Staff::factory()->create(['role_id' => $role->role_id]);
    }

    public function test_an_admin_can_create_an_add_on_group(): void
    {
        $admin = $this->admin();
        $item = MenuItem::factory()->create();

        $response = $this->actingAs($admin, 'staff')->post("/admin/menu-items/{$item->item_id}/groups", [
            'group_name' => 'Sauce',
            'min_select' => 0,
            'max_select' => 1,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('add_on_group', ['item_id' => $item->item_id, 'group_name' => 'Sauce']);
    }

    public function test_max_select_must_be_at_least_min_select(): void
    {
        $admin = $this->admin();
        $item = MenuItem::factory()->create();

        $response = $this->actingAs($admin, 'staff')->post("/admin/menu-items/{$item->item_id}/groups", [
            'group_name' => 'Sauce',
            'min_select' => 2,
            'max_select' => 1,
        ]);

        $response->assertSessionHasErrors('max_select');
    }

    public function test_a_required_group_needs_a_minimum_of_at_least_one(): void
    {
        $admin = $this->admin();
        $item = MenuItem::factory()->create();

        $response = $this->actingAs($admin, 'staff')->post("/admin/menu-items/{$item->item_id}/groups", [
            'group_name' => 'Sauce',
            'is_required' => '1',
            'min_select' => 0,
            'max_select' => 1,
        ]);

        $response->assertSessionHasErrors('min_select');
    }

    public function test_an_admin_can_add_an_option_to_a_group(): void
    {
        $admin = $this->admin();
        $item = MenuItem::factory()->create();
        $group = $item->addOnGroups()->create(['group_name' => 'Sauce', 'max_select' => 1]);

        $response = $this->actingAs($admin, 'staff')->post("/admin/menu-items/{$item->item_id}/groups/{$group->group_id}/options", [
            'option_name' => 'BBQ',
            'price_delta' => 0.50,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('add_on_option', ['group_id' => $group->group_id, 'option_name' => 'BBQ']);
    }

    public function test_deleting_a_group_cascades_to_its_options(): void
    {
        $admin = $this->admin();
        $item = MenuItem::factory()->create();
        $group = $item->addOnGroups()->create(['group_name' => 'Sauce', 'max_select' => 1]);
        $option = $group->options()->create(['option_name' => 'BBQ', 'price_delta' => 0.5]);

        $this->actingAs($admin, 'staff')->delete("/admin/menu-items/{$item->item_id}/groups/{$group->group_id}");

        $this->assertDatabaseMissing('add_on_group', ['group_id' => $group->group_id]);
        $this->assertDatabaseMissing('add_on_option', ['option_id' => $option->option_id]);
    }
}
