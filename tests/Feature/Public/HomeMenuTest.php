<?php

namespace Tests\Feature\Public;

use App\Models\DietaryTag;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomeMenuTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SettingSeeder::class);
    }

    public function test_featured_menu_items_render_with_prices_and_dietary_tags(): void
    {
        $category = MenuCategory::factory()->create(['category_name' => 'Pizzas', 'is_active' => true]);

        $featured = MenuItem::factory()->create([
            'category_id' => $category->category_id,
            'item_name' => 'Margherita Deluxe',
            'description' => 'San Marzano tomatoes and basil',
            'is_featured' => true,
            'is_active' => true,
            'is_available' => true,
        ]);
        $featured->sizes()->create([
            'size_name' => 'Regular',
            'price' => 19.50,
            'is_active' => true,
        ]);

        $tag = DietaryTag::factory()->create(['tag_name' => 'Vegetarian', 'is_active' => true]);
        $featured->dietaryTags()->attach($tag->dietary_tag_id);

        $nonFeatured = MenuItem::factory()->create([
            'category_id' => $category->category_id,
            'item_name' => 'Secret Dish',
            'is_featured' => false,
            'is_active' => true,
        ]);
        $nonFeatured->sizes()->create([
            'size_name' => 'Regular',
            'price' => 25.00,
            'is_active' => true,
        ]);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Margherita Deluxe');
        $response->assertSee('$19.50');
        $response->assertSee('title="Vegetarian"', false);
        $response->assertDontSee('Secret Dish');
    }

    public function test_specials_offer_block_renders_when_active_sale_exists(): void
    {
        $category = MenuCategory::factory()->create(['is_active' => true]);
        $item = MenuItem::factory()->create([
            'category_id' => $category->category_id,
            'item_name' => 'Special Burger',
            'description' => 'Juicy double beef',
            'is_active' => true,
        ]);
        $item->sizes()->create([
            'size_name' => 'Regular',
            'price' => 22.00,
            'sale_price' => 18.00,
            'sale_starts_at' => now()->subDay(),
            'sale_ends_at' => now()->addDays(2),
            'is_active' => true,
        ]);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('data-testid="home-offer-block"', false);
        $response->assertSee('Special Burger');
        $response->assertSee('$18.00');
        $response->assertSee('$22.00');
        $response->assertSee('data-testid="home-filter-specials"', false);
    }

    public function test_specials_offer_block_hidden_when_no_active_sales(): void
    {
        $category = MenuCategory::factory()->create(['is_active' => true]);
        $item = MenuItem::factory()->create([
            'category_id' => $category->category_id,
            'is_active' => true,
        ]);
        $item->sizes()->create([
            'size_name' => 'Regular',
            'price' => 20.00,
            'sale_price' => null,
            'is_active' => true,
        ]);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertDontSee('data-testid="home-offer-block"', false);
        $response->assertDontSee('data-testid="home-filter-specials"', false);
    }

    public function test_sold_out_state_shows_on_unavailable_featured_items(): void
    {
        $category = MenuCategory::factory()->create(['is_active' => true]);
        $soldOutItem = MenuItem::factory()->create([
            'category_id' => $category->category_id,
            'item_name' => 'Market Fish',
            'is_featured' => true,
            'is_active' => true,
            'is_available' => false,
        ]);
        $soldOutItem->sizes()->create([
            'size_name' => 'Regular',
            'price' => 34.00,
            'is_active' => true,
        ]);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Market Fish');
        $response->assertSee('is-soldout');
        $response->assertSee('Sold out tonight');
    }
}
