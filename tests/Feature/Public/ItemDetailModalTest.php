<?php

namespace Tests\Feature\Public;

use App\Models\AddOnGroup;
use App\Models\AddOnOption;
use App\Models\Allergen;
use App\Models\Customer;
use App\Models\DietaryTag;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\MenuItemSize;
use App\Models\RestaurantTable;
use App\Models\Setting;
use App\Services\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Task 67: Item detail modal (S03) — sizes, add-ons, nutrition (FR34, FR35, BR16, BR17, BR20, BR57, BR58).
 */
class ItemDetailModalTest extends TestCase
{
    use RefreshDatabase;

    private function createSampleDish(array $attributes = []): MenuItem
    {
        $category = MenuCategory::factory()->create(['is_active' => true]);

        return MenuItem::factory()->create(array_merge([
            'category_id' => $category->category_id,
            'item_name' => 'Signature Wagyu Burger',
            'description' => 'Juicy wagyu patty with artisan brioche bun',
            'is_available' => true,
            'is_active' => true,
            'calories_kcal' => 750,
            'protein_g' => 45,
            'carbohydrates_g' => 60,
            'fat_g' => 30,
        ], $attributes));
    }

    public function test_each_dish_has_an_anchor_matching_its_modal_so_ai_chip_links_resolve(): void
    {
        $item = $this->createSampleDish();

        $this->get('/menu')
            ->assertOk()
            ->assertSee('id="item-'.$item->item_id.'"', false)
            ->assertSee('id="item-modal-'.$item->item_id.'"', false);
    }

    public function test_modal_renders_sizes_addons_and_nutrition(): void
    {
        $item = $this->createSampleDish();

        $regular = MenuItemSize::create([
            'item_id' => $item->item_id,
            'size_name' => 'Regular',
            'price' => 22.00,
            'is_active' => true,
        ]);

        $double = MenuItemSize::create([
            'item_id' => $item->item_id,
            'size_name' => 'Double Patty',
            'price' => 28.00,
            'is_active' => true,
        ]);

        $group = AddOnGroup::create([
            'item_id' => $item->item_id,
            'group_name' => 'Choice of Sauce',
            'is_required' => true,
            'min_select' => 1,
            'max_select' => 1,
        ]);

        AddOnOption::create([
            'group_id' => $group->group_id,
            'option_name' => 'Truffle Mayo',
            'price_delta' => 2.50,
            'is_available' => true,
            'is_active' => true,
        ]);

        AddOnOption::create([
            'group_id' => $group->group_id,
            'option_name' => 'Smoky BBQ',
            'price_delta' => 0.00,
            'is_available' => true,
            'is_active' => true,
        ]);

        $allergen = Allergen::factory()->create(['allergen_name' => 'Gluten', 'is_active' => true]);
        $item->allergens()->attach($allergen);

        $dietary = DietaryTag::factory()->create(['tag_name' => 'Halal', 'is_active' => true]);
        $item->dietaryTags()->attach($dietary);

        $response = $this->get(route('menu.index'));

        $response->assertOk();
        $response->assertSee('Signature Wagyu Burger');
        $response->assertSee('data-testid="item-modal-'.$item->item_id.'"', false);
        $response->assertSee('data-testid="item-modal-nutrition-'.$item->item_id.'"', false);
        $response->assertSee('750');
        $response->assertSee('45g');
        $response->assertSee('60g');
        $response->assertSee('30g');
        $response->assertSee('Regular');
        $response->assertSee('Double Patty');
        $response->assertSee('$22.00');
        $response->assertSee('$28.00');
        $response->assertSee('Choice of Sauce');
        $response->assertSee('Required (min 1)');
        $response->assertSee('Truffle Mayo');
        $response->assertSee('+$2.50');
        $response->assertSee('Smoky BBQ');
        $response->assertSee('Free');
        $response->assertSee('Gluten');
        $response->assertSee('Halal');
    }

    public function test_modal_displays_sale_price_strikethrough_when_size_is_on_special(): void
    {
        $item = $this->createSampleDish();

        MenuItemSize::create([
            'item_id' => $item->item_id,
            'size_name' => 'Standard',
            'price' => 25.00,
            'sale_price' => 19.00,
            'sale_starts_at' => now()->subDay(),
            'sale_ends_at' => now()->addDay(),
            'is_active' => true,
        ]);

        $response = $this->get(route('menu.index'));

        $response->assertOk();
        $response->assertSee('$25.00');
        $response->assertSee('$19.00');
    }

    public function test_modal_displays_scan_prompt_outside_table_context(): void
    {
        $item = $this->createSampleDish();
        MenuItemSize::create([
            'item_id' => $item->item_id,
            'size_name' => 'Standard',
            'price' => 20.00,
            'is_active' => true,
        ]);

        $response = $this->get(route('menu.index'));

        $response->assertOk();
        $response->assertSee('data-testid="item-modal-no-table-'.$item->item_id.'"', false);
        $response->assertSee('Scan the QR code on your table to order.');
        $response->assertDontSee('data-testid="item-modal-add-to-cart-'.$item->item_id.'"', false);
    }

    public function test_modal_displays_add_to_cart_form_with_table_context(): void
    {
        $table = RestaurantTable::factory()->create();
        $item = $this->createSampleDish();
        MenuItemSize::create([
            'item_id' => $item->item_id,
            'size_name' => 'Standard',
            'price' => 20.00,
            'is_active' => true,
        ]);

        $response = $this->withSession(['table_id' => $table->table_id])
            ->get(route('menu.index'));

        $response->assertOk();
        $response->assertSee('data-testid="item-modal-form-'.$item->item_id.'"', false);
        $response->assertSee('data-testid="item-modal-add-to-cart-'.$item->item_id.'"', false);
        $response->assertSee('Add to cart');
        $response->assertSee('data-testid="item-modal-qty-'.$item->item_id.'"', false);
        $response->assertSee('data-testid="item-modal-special-request-'.$item->item_id.'"', false);
    }

    public function test_modal_displays_paused_notice_when_qr_ordering_disabled(): void
    {
        $table = RestaurantTable::factory()->create();
        Setting::updateOrCreate(
            ['setting_key' => 'qr_ordering_enabled'],
            ['setting_value' => '0', 'value_type' => 'bool']
        );

        $item = $this->createSampleDish();
        MenuItemSize::create([
            'item_id' => $item->item_id,
            'size_name' => 'Standard',
            'price' => 20.00,
            'is_active' => true,
        ]);

        $response = $this->withSession(['table_id' => $table->table_id])
            ->get(route('menu.index'));

        $response->assertOk();
        $response->assertSee('data-testid="item-modal-paused-'.$item->item_id.'"', false);
        $response->assertSee('Online ordering is temporarily paused');
        $response->assertDontSee('data-testid="item-modal-add-to-cart-'.$item->item_id.'"', false);
    }

    public function test_modal_displays_soldout_when_item_is_unavailable(): void
    {
        $table = RestaurantTable::factory()->create();
        $item = $this->createSampleDish(['is_available' => false]);
        MenuItemSize::create([
            'item_id' => $item->item_id,
            'size_name' => 'Standard',
            'price' => 20.00,
            'is_active' => true,
        ]);

        $response = $this->withSession(['table_id' => $table->table_id])
            ->get(route('menu.index'));

        $response->assertOk();
        $response->assertSee('data-testid="item-modal-soldout-'.$item->item_id.'"', false);
        $response->assertSee('Sold out tonight');
        $response->assertDontSee('data-testid="item-modal-add-to-cart-'.$item->item_id.'"', false);
    }

    public function test_modal_form_submission_adds_item_to_cart(): void
    {
        $customer = Customer::factory()->create();
        $table = RestaurantTable::factory()->create();
        $item = $this->createSampleDish();

        $size = MenuItemSize::create([
            'item_id' => $item->item_id,
            'size_name' => 'Standard',
            'price' => 22.00,
            'is_active' => true,
        ]);

        $group = AddOnGroup::create([
            'item_id' => $item->item_id,
            'group_name' => 'Sauce',
            'is_required' => true,
            'min_select' => 1,
            'max_select' => 1,
        ]);

        $option = AddOnOption::create([
            'group_id' => $group->group_id,
            'option_name' => 'Aioli',
            'price_delta' => 1.50,
            'is_available' => true,
            'is_active' => true,
        ]);

        $response = $this->actingAs($customer, 'customer')
            ->withSession(['table_id' => $table->table_id])
            ->post(route('cart.lines.store'), [
                'item_id' => $item->item_id,
                'size_id' => $size->size_id,
                'add_on_option_ids' => [$option->option_id],
                'quantity' => 2,
                'special_request' => 'Crispy bun please',
            ]);

        $response->assertRedirect(route('cart.index'));
        $response->assertSessionHas('status', 'item-added');

        $lines = app(CartService::class)->lines();
        $this->assertCount(1, $lines);
        $this->assertSame(2, $lines[0]['quantity']);
        $this->assertSame('Crispy bun please', $lines[0]['special_request']);
        $this->assertSame(23.50, $lines[0]['unit_price']); // 22 + 1.50
        $this->assertSame(47.00, $lines[0]['line_total']); // 23.50 * 2
    }
}
