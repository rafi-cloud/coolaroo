<?php

namespace Tests\Feature\Admin;

use App\Enums\Destination;
use App\Models\AuditLog;
use App\Models\HistoricalDataManagement;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Role;
use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): Staff
    {
        $role = Role::factory()->admin()->create();

        return Staff::factory()->create(['role_id' => $role->role_id]);
    }

    public function test_an_admin_can_create_a_top_level_category(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin, 'staff')->post('/admin/categories', [
            'category_name' => 'Mains',
            'display_order' => 1,
        ]);

        $response->assertRedirect('/admin/categories');
        $this->assertDatabaseHas('menu_category', ['category_name' => 'Mains', 'parent_category_id' => null]);
    }

    public function test_a_category_can_be_a_child_of_a_top_level_category(): void
    {
        $admin = $this->admin();
        $parent = MenuCategory::factory()->create();

        $this->actingAs($admin, 'staff')->post('/admin/categories', [
            'category_name' => 'Pasta',
            'parent_category_id' => $parent->category_id,
        ]);

        $this->assertDatabaseHas('menu_category', ['category_name' => 'Pasta', 'parent_category_id' => $parent->category_id]);
    }

    public function test_a_subcategory_cannot_be_chosen_as_a_parent(): void
    {
        $admin = $this->admin();
        $topLevel = MenuCategory::factory()->create();
        $subcategory = MenuCategory::factory()->create(['parent_category_id' => $topLevel->category_id]);

        $response = $this->actingAs($admin, 'staff')->post('/admin/categories', [
            'category_name' => 'Should fail',
            'parent_category_id' => $subcategory->category_id,
        ]);

        $response->assertSessionHasErrors('parent_category_id');
    }

    public function test_deactivating_a_category_writes_an_audit_log_and_a_snapshot(): void
    {
        $admin = $this->admin();
        $category = MenuCategory::factory()->create();

        $this->actingAs($admin, 'staff')->patch("/admin/categories/{$category->category_id}/deactivate", [
            'reason' => 'Seasonal',
        ]);

        $this->assertFalse($category->fresh()->is_active);

        $log = AuditLog::where('entity_name', 'menu_category')->where('entity_id', $category->category_id)->first();
        $this->assertNotNull($log);
        $this->assertSame('category_deactivate', $log->action_type);

        $this->assertNotNull(HistoricalDataManagement::where('log_id', $log->log_id)->first());
    }

    public function test_reactivating_a_category_does_not_write_a_snapshot(): void
    {
        $admin = $this->admin();
        $category = MenuCategory::factory()->create(['is_active' => false]);

        $this->actingAs($admin, 'staff')->patch("/admin/categories/{$category->category_id}/reactivate");

        $this->assertTrue($category->fresh()->is_active);

        $log = AuditLog::where('entity_name', 'menu_category')->where('action_type', 'category_reactivate')->first();
        $this->assertNotNull($log);
        $this->assertNull(HistoricalDataManagement::where('log_id', $log->log_id)->first());
    }

    public function test_an_empty_category_can_be_deleted(): void
    {
        $admin = $this->admin();
        $category = MenuCategory::factory()->create();

        $response = $this->actingAs($admin, 'staff')->delete("/admin/categories/{$category->category_id}");

        $response->assertRedirect('/admin/categories');
        $this->assertDatabaseMissing('menu_category', ['category_id' => $category->category_id]);
    }

    public function test_a_category_with_children_cannot_be_deleted(): void
    {
        $admin = $this->admin();
        $parent = MenuCategory::factory()->create();
        MenuCategory::factory()->create(['parent_category_id' => $parent->category_id]);

        $response = $this->actingAs($admin, 'staff')->delete("/admin/categories/{$parent->category_id}");

        $response->assertSessionHasErrors('category');
        $this->assertDatabaseHas('menu_category', ['category_id' => $parent->category_id]);
    }

    public function test_a_category_with_menu_items_cannot_be_deleted(): void
    {
        $admin = $this->admin();
        $category = MenuCategory::factory()->create();
        MenuItem::create([
            'category_id' => $category->category_id,
            'item_name' => 'Margherita',
            'destination' => Destination::Kitchen,
        ]);

        $response = $this->actingAs($admin, 'staff')->delete("/admin/categories/{$category->category_id}");

        $response->assertSessionHasErrors('category');
        $this->assertDatabaseHas('menu_category', ['category_id' => $category->category_id]);
    }

    public function test_a_non_admin_cannot_reach_the_category_list(): void
    {
        $role = Role::factory()->waitstaff()->create();
        $waiter = Staff::factory()->create(['role_id' => $role->role_id]);

        $this->actingAs($waiter, 'staff')->get('/admin/categories')->assertForbidden();
    }
}
