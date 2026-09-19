<?php

namespace Tests\Feature\Admin;

use App\Models\MenuItem;
use App\Models\Role;
use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MenuItemSizeTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): Staff
    {
        $role = Role::factory()->admin()->create();

        return Staff::factory()->create(['role_id' => $role->role_id]);
    }

    public function test_an_admin_can_add_a_size(): void
    {
        $admin = $this->admin();
        $item = MenuItem::factory()->create();

        $response = $this->actingAs($admin, 'staff')->post("/admin/menu-items/{$item->item_id}/sizes", [
            'size_name' => 'Large',
            'price' => 18.90,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('menu_item_size', ['item_id' => $item->item_id, 'size_name' => 'Large']);
    }

    public function test_a_sale_price_must_be_less_than_the_regular_price(): void
    {
        $admin = $this->admin();
        $item = MenuItem::factory()->create();

        $response = $this->actingAs($admin, 'staff')->post("/admin/menu-items/{$item->item_id}/sizes", [
            'size_name' => 'Large',
            'price' => 10,
            'sale_price' => 15,
        ]);

        $response->assertSessionHasErrors('sale_price');
    }

    public function test_a_non_admin_cannot_add_a_size(): void
    {
        $role = Role::factory()->waitstaff()->create();
        $waiter = Staff::factory()->create(['role_id' => $role->role_id]);
        $item = MenuItem::factory()->create();

        $this->actingAs($waiter, 'staff')->post("/admin/menu-items/{$item->item_id}/sizes", [
            'size_name' => 'Large',
            'price' => 18.90,
        ])->assertForbidden();
    }

    public function test_the_only_size_cannot_be_deleted(): void
    {
        $admin = $this->admin();
        $item = MenuItem::factory()->withSize()->create();
        $size = $item->sizes()->first();

        $response = $this->actingAs($admin, 'staff')->delete("/admin/menu-items/{$item->item_id}/sizes/{$size->size_id}");

        $response->assertSessionHasErrors('size');
        $this->assertDatabaseHas('menu_item_size', ['size_id' => $size->size_id]);
    }

    public function test_a_size_can_be_deleted_when_another_remains(): void
    {
        $admin = $this->admin();
        $item = MenuItem::factory()->withSize()->create();
        $second = $item->sizes()->create(['size_name' => 'Large', 'price' => 18.90]);

        $response = $this->actingAs($admin, 'staff')->delete("/admin/menu-items/{$item->item_id}/sizes/{$second->size_id}");

        $response->assertRedirect();
        $this->assertDatabaseMissing('menu_item_size', ['size_id' => $second->size_id]);
    }
}
