<?php

namespace Tests\Feature\Admin;

use App\Enums\Destination;
use App\Models\DietaryTag;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Role;
use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DietaryTagTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): Staff
    {
        $role = Role::factory()->admin()->create();

        return Staff::factory()->create(['role_id' => $role->role_id]);
    }

    public function test_an_admin_can_create_a_dietary_tag(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin, 'staff')->post('/admin/dietary-tags', [
            'tag_name' => 'Vegetarian',
        ]);

        $response->assertRedirect('/admin/dietary-tags');
        $this->assertDatabaseHas('dietary_tag', ['tag_name' => 'Vegetarian']);
    }

    public function test_tag_names_must_be_unique(): void
    {
        $admin = $this->admin();
        DietaryTag::factory()->create(['tag_name' => 'Halal']);

        $response = $this->actingAs($admin, 'staff')->post('/admin/dietary-tags', [
            'tag_name' => 'Halal',
        ]);

        $response->assertSessionHasErrors('tag_name');
    }

    public function test_an_unused_tag_can_be_deleted(): void
    {
        $admin = $this->admin();
        $tag = DietaryTag::factory()->create();

        $this->actingAs($admin, 'staff')->delete("/admin/dietary-tags/{$tag->dietary_tag_id}");

        $this->assertDatabaseMissing('dietary_tag', ['dietary_tag_id' => $tag->dietary_tag_id]);
    }

    public function test_a_tag_used_by_a_menu_item_cannot_be_deleted(): void
    {
        $admin = $this->admin();
        $tag = DietaryTag::factory()->create();
        $category = MenuCategory::factory()->create();
        $item = MenuItem::create([
            'category_id' => $category->category_id,
            'item_name' => 'Veggie Burger',
            'destination' => Destination::Kitchen,
        ]);
        $item->dietaryTags()->attach($tag->dietary_tag_id);

        $response = $this->actingAs($admin, 'staff')->delete("/admin/dietary-tags/{$tag->dietary_tag_id}");

        $response->assertSessionHasErrors('tag');
        $this->assertDatabaseHas('dietary_tag', ['dietary_tag_id' => $tag->dietary_tag_id]);
    }
}
