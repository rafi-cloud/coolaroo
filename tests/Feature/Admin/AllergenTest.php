<?php

namespace Tests\Feature\Admin;

use App\Enums\Destination;
use App\Models\Allergen;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Role;
use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AllergenTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): Staff
    {
        $role = Role::factory()->admin()->create();

        return Staff::factory()->create(['role_id' => $role->role_id]);
    }

    public function test_an_admin_can_create_an_allergen(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin, 'staff')->post('/admin/allergens', [
            'allergen_name' => 'Peanuts',
        ]);

        $response->assertRedirect('/admin/allergens');
        $this->assertDatabaseHas('allergen', ['allergen_name' => 'Peanuts']);
    }

    public function test_allergen_names_must_be_unique(): void
    {
        $admin = $this->admin();
        Allergen::factory()->create(['allergen_name' => 'Dairy']);

        $response = $this->actingAs($admin, 'staff')->post('/admin/allergens', [
            'allergen_name' => 'Dairy',
        ]);

        $response->assertSessionHasErrors('allergen_name');
    }

    public function test_an_unused_allergen_can_be_deleted(): void
    {
        $admin = $this->admin();
        $allergen = Allergen::factory()->create();

        $this->actingAs($admin, 'staff')->delete("/admin/allergens/{$allergen->allergen_id}");

        $this->assertDatabaseMissing('allergen', ['allergen_id' => $allergen->allergen_id]);
    }

    public function test_an_allergen_used_by_a_menu_item_cannot_be_deleted(): void
    {
        $admin = $this->admin();
        $allergen = Allergen::factory()->create();
        $category = MenuCategory::factory()->create();
        $item = MenuItem::create([
            'category_id' => $category->category_id,
            'item_name' => 'Pad Thai',
            'destination' => Destination::Kitchen,
        ]);
        $item->allergens()->attach($allergen->allergen_id);

        $response = $this->actingAs($admin, 'staff')->delete("/admin/allergens/{$allergen->allergen_id}");

        $response->assertSessionHasErrors('allergen');
        $this->assertDatabaseHas('allergen', ['allergen_id' => $allergen->allergen_id]);
    }
}
