<?php

namespace Tests\Feature\Admin;

use App\Models\AuditLog;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Role;
use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MenuItemTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): Staff
    {
        $role = Role::factory()->admin()->create();

        return Staff::factory()->create(['role_id' => $role->role_id]);
    }

    public function test_an_admin_can_create_a_menu_item_with_image_and_tags(): void
    {
        Storage::fake('public');
        $admin = $this->admin();
        $category = MenuCategory::factory()->create();

        $response = $this->actingAs($admin, 'staff')->post('/admin/menu-items', [
            'item_name' => 'Margherita',
            'category_id' => $category->category_id,
            'destination' => 'kitchen',
            'image' => UploadedFile::fake()->image('pizza.jpg', 800, 800),
        ]);

        $response->assertRedirect();
        $item = MenuItem::where('item_name', 'Margherita')->firstOrFail();
        $this->assertNotNull($item->image_path);
        Storage::disk('public')->assertExists($item->image_path);
    }

    public function test_creating_a_menu_item_fails_without_required_fields(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin, 'staff')->post('/admin/menu-items', []);

        $response->assertSessionHasErrors(['item_name', 'category_id', 'destination', 'image']);
    }

    public function test_a_non_admin_cannot_reach_the_menu_item_list(): void
    {
        $role = Role::factory()->waitstaff()->create();
        $waiter = Staff::factory()->create(['role_id' => $role->role_id]);

        $this->actingAs($waiter, 'staff')->get('/admin/menu-items')->assertForbidden();
    }

    public function test_archiving_a_menu_item_writes_an_audit_snapshot(): void
    {
        $admin = $this->admin();
        $item = MenuItem::factory()->create();

        $this->actingAs($admin, 'staff')->patch("/admin/menu-items/{$item->item_id}/archive");

        $this->assertFalse($item->fresh()->is_active);
        $this->assertNotNull(AuditLog::where('entity_name', 'menu_item')->where('action_type', 'menu_item_archive')->first());
    }

    public function test_the_thirteenth_featured_item_is_rejected(): void
    {
        $admin = $this->admin();
        MenuItem::factory()->count(12)->create(['is_featured' => true]);
        $thirteenth = MenuItem::factory()->create(['is_featured' => false]);

        $response = $this->actingAs($admin, 'staff')->patch("/admin/menu-items/{$thirteenth->item_id}/toggle-featured");

        $response->assertSessionHasErrors('featured');
        $this->assertFalse($thirteenth->fresh()->is_featured);
    }
}
