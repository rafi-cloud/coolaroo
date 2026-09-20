<?php

namespace Tests\Feature\Public;

use App\Models\Allergen;
use App\Models\DietaryTag;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\RestaurantTable;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MenuPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SettingSeeder::class);
    }

    public function test_menu_page_loads_and_displays_categories_and_items(): void
    {
        $cat = MenuCategory::factory()->create(['category_name' => 'Grill', 'is_active' => true]);
        $item = MenuItem::factory()->create([
            'category_id' => $cat->category_id,
            'item_name' => 'Porterhouse Steak',
            'description' => 'Grass-fed 300g steak',
            'is_active' => true,
        ]);
        $item->sizes()->create([
            'size_name' => 'Regular',
            'price' => 38.00,
            'is_active' => true,
        ]);

        $response = $this->get('/menu');

        $response->assertOk();
        $response->assertViewIs('public.menu');
        $response->assertSee('Our Menu');
        $response->assertSee('Porterhouse Steak');
        $response->assertSee('$38.00');
        $response->assertSee('data-testid="menu-allergen-disclaimer"', false);
    }

    public function test_menu_filters_by_category(): void
    {
        $pizzaCat = MenuCategory::factory()->create(['category_name' => 'Pizza', 'is_active' => true]);
        $pastaCat = MenuCategory::factory()->create(['category_name' => 'Pasta', 'is_active' => true]);

        $pizza = MenuItem::factory()->create([
            'category_id' => $pizzaCat->category_id,
            'item_name' => 'Calzone',
            'is_active' => true,
        ]);
        $pizza->sizes()->create(['size_name' => 'Regular', 'price' => 20, 'is_active' => true]);

        $pasta = MenuItem::factory()->create([
            'category_id' => $pastaCat->category_id,
            'item_name' => 'Gnocchi Sorrentina',
            'is_active' => true,
        ]);
        $pasta->sizes()->create(['size_name' => 'Regular', 'price' => 22, 'is_active' => true]);

        $response = $this->get("/menu?category={$pizzaCat->category_id}");

        $response->assertOk();
        $response->assertSee('Calzone');
        $response->assertDontSee('Gnocchi Sorrentina');
    }

    public function test_menu_filters_by_specials(): void
    {
        $cat = MenuCategory::factory()->create(['is_active' => true]);

        $specialItem = MenuItem::factory()->create([
            'category_id' => $cat->category_id,
            'item_name' => 'Special Ribs',
            'is_active' => true,
        ]);
        $specialItem->sizes()->create([
            'size_name' => 'Full Rack',
            'price' => 40.00,
            'sale_price' => 28.00,
            'sale_starts_at' => now()->subDay(),
            'sale_ends_at' => now()->addDays(2),
            'is_active' => true,
        ]);

        $regularItem = MenuItem::factory()->create([
            'category_id' => $cat->category_id,
            'item_name' => 'Normal Wings',
            'is_active' => true,
        ]);
        $regularItem->sizes()->create([
            'size_name' => '12pc',
            'price' => 18.00,
            'is_active' => true,
        ]);

        $response = $this->get('/menu?category=specials');

        $response->assertOk();
        $response->assertSee('Special Ribs');
        $response->assertSee('$28.00');
        $response->assertDontSee('Normal Wings');
    }

    public function test_menu_filters_by_dietary_tag(): void
    {
        $cat = MenuCategory::factory()->create(['is_active' => true]);
        $vegTag = DietaryTag::factory()->create(['tag_name' => 'Vegetarian', 'is_active' => true]);

        $vegItem = MenuItem::factory()->create([
            'category_id' => $cat->category_id,
            'item_name' => 'Garden Bowl',
            'is_active' => true,
        ]);
        $vegItem->sizes()->create(['size_name' => 'Regular', 'price' => 16, 'is_active' => true]);
        $vegItem->dietaryTags()->attach($vegTag->dietary_tag_id);

        $meatItem = MenuItem::factory()->create([
            'category_id' => $cat->category_id,
            'item_name' => 'Beef Brisket',
            'is_active' => true,
        ]);
        $meatItem->sizes()->create(['size_name' => 'Regular', 'price' => 28, 'is_active' => true]);

        $response = $this->get("/menu?dietary[]={$vegTag->dietary_tag_id}");

        $response->assertOk();
        $response->assertSee('Garden Bowl');
        $response->assertDontSee('Beef Brisket');
    }

    public function test_menu_excludes_allergens(): void
    {
        $cat = MenuCategory::factory()->create(['is_active' => true]);
        $peanut = Allergen::factory()->create(['allergen_name' => 'Peanuts', 'is_active' => true]);

        $peanutDish = MenuItem::factory()->create([
            'category_id' => $cat->category_id,
            'item_name' => 'Satay Skewers',
            'is_active' => true,
        ]);
        $peanutDish->sizes()->create(['size_name' => 'Regular', 'price' => 18, 'is_active' => true]);
        $peanutDish->allergens()->attach($peanut->allergen_id);

        $safeDish = MenuItem::factory()->create([
            'category_id' => $cat->category_id,
            'item_name' => 'Plain Fries',
            'is_active' => true,
        ]);
        $safeDish->sizes()->create(['size_name' => 'Regular', 'price' => 8, 'is_active' => true]);

        $response = $this->get("/menu?exclude_allergen[]={$peanut->allergen_id}");

        $response->assertOk();
        $response->assertSee('Plain Fries');
        $response->assertDontSee('Satay Skewers');
    }

    public function test_menu_displays_nutrition_information_when_recorded(): void
    {
        $cat = MenuCategory::factory()->create(['is_active' => true]);
        $item = MenuItem::factory()->create([
            'category_id' => $cat->category_id,
            'item_name' => 'Healthy Salmon',
            'calories_kcal' => 520,
            'protein_g' => 42,
            'carbohydrates_g' => 12,
            'fat_g' => 24,
            'is_active' => true,
        ]);
        $item->sizes()->create(['size_name' => 'Regular', 'price' => 32, 'is_active' => true]);

        $response = $this->get('/menu');

        $response->assertOk();
        $response->assertSee('520 kcal');
        $response->assertSee('P: 42g');
        $response->assertSee('C: 12g');
        $response->assertSee('F: 24g');
    }

    public function test_menu_renders_with_table_chip_when_table_id_in_session(): void
    {
        $table = RestaurantTable::factory()->create(['table_number' => 12]);

        $response = $this->withSession(['table_id' => $table->table_id])->get('/menu');

        $response->assertOk();
        $response->assertSee('Table 12');
        $response->assertSee('class="order-bar"', false);
    }
}
