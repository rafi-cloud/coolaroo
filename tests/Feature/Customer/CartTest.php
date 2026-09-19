<?php

namespace Tests\Feature\Customer;

use App\Models\AddOnGroup;
use App\Models\Customer;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\RestaurantTable;
use App\Models\Setting;
use App\Services\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CartTest extends TestCase
{
    use RefreshDatabase;

    private function itemWithSize(): array
    {
        $item = MenuItem::factory()->create(['category_id' => MenuCategory::factory()]);
        $size = $item->sizes()->create(['size_name' => 'Regular', 'price' => 12]);

        return [$item, $size];
    }

    private function withTable(): void
    {
        $table = RestaurantTable::factory()->create();
        session(['table_id' => $table->table_id]);
    }

    public function test_adding_an_item_requires_table_context(): void
    {
        $customer = Customer::factory()->create();
        [$item, $size] = $this->itemWithSize();

        $response = $this->actingAs($customer, 'customer')->post('/cart/lines', [
            'item_id' => $item->item_id,
            'size_id' => $size->size_id,
            'quantity' => 1,
        ]);

        $response->assertSessionHas('error');
        $this->assertEmpty(app(CartService::class)->lines());
    }

    public function test_adding_an_item_with_table_context_succeeds(): void
    {
        $customer = Customer::factory()->create();
        $this->withTable();
        [$item, $size] = $this->itemWithSize();

        $this->actingAs($customer, 'customer')->post('/cart/lines', [
            'item_id' => $item->item_id,
            'size_id' => $size->size_id,
            'quantity' => 2,
        ]);

        $this->assertCount(1, app(CartService::class)->lines());
    }

    public function test_cart_is_blocked_when_qr_ordering_is_paused(): void
    {
        $customer = Customer::factory()->create();
        $this->withTable();
        Setting::updateOrCreate(['setting_key' => 'qr_ordering_enabled'], ['setting_value' => '0', 'value_type' => 'bool']);
        [$item, $size] = $this->itemWithSize();

        $response = $this->actingAs($customer, 'customer')->post('/cart/lines', [
            'item_id' => $item->item_id,
            'size_id' => $size->size_id,
            'quantity' => 1,
        ]);

        $response->assertSessionHas('error');
    }

    public function test_an_add_on_group_below_its_minimum_selection_is_rejected(): void
    {
        $customer = Customer::factory()->create();
        $this->withTable();
        [$item, $size] = $this->itemWithSize();
        AddOnGroup::create(['item_id' => $item->item_id, 'group_name' => 'Sauce', 'is_required' => true, 'min_select' => 1, 'max_select' => 1]);

        $response = $this->actingAs($customer, 'customer')->post('/cart/lines', [
            'item_id' => $item->item_id,
            'size_id' => $size->size_id,
            'quantity' => 1,
            'add_on_option_ids' => [],
        ]);

        $response->assertSessionHasErrors('add_on_option_ids');
    }

    public function test_a_valid_add_on_selection_is_accepted(): void
    {
        $customer = Customer::factory()->create();
        $this->withTable();
        [$item, $size] = $this->itemWithSize();
        $group = AddOnGroup::create(['item_id' => $item->item_id, 'group_name' => 'Sauce', 'min_select' => 1, 'max_select' => 1]);
        $option = $group->options()->create(['option_name' => 'BBQ', 'price_delta' => 0.5]);

        $response = $this->actingAs($customer, 'customer')->post('/cart/lines', [
            'item_id' => $item->item_id,
            'size_id' => $size->size_id,
            'quantity' => 1,
            'add_on_option_ids' => [$option->option_id],
        ]);

        $response->assertSessionDoesntHaveErrors();
    }

    public function test_line_total_includes_add_on_price_deltas(): void
    {
        $customer = Customer::factory()->create();
        $this->withTable();
        [$item, $size] = $this->itemWithSize();
        $group = AddOnGroup::create(['item_id' => $item->item_id, 'group_name' => 'Extras', 'max_select' => 1]);
        $option = $group->options()->create(['option_name' => 'Extra cheese', 'price_delta' => 2]);

        $this->actingAs($customer, 'customer')->post('/cart/lines', [
            'item_id' => $item->item_id,
            'size_id' => $size->size_id,
            'quantity' => 2,
            'add_on_option_ids' => [$option->option_id],
        ]);

        $this->assertSame(28.0, app(CartService::class)->total());
    }

    public function test_updating_a_line_changes_its_quantity(): void
    {
        $customer = Customer::factory()->create();
        $this->withTable();
        [$item, $size] = $this->itemWithSize();
        $cart = app(CartService::class);
        $lineId = $cart->add($item->item_id, $size->size_id, [], 1, null);

        $this->actingAs($customer, 'customer')->patch("/cart/lines/{$lineId}", ['quantity' => 5]);

        $lines = app(CartService::class)->lines();
        $this->assertSame(5, $lines[0]['quantity']);
    }

    public function test_removing_a_line_clears_it_from_the_cart(): void
    {
        $customer = Customer::factory()->create();
        $this->withTable();
        [$item, $size] = $this->itemWithSize();
        $cart = app(CartService::class);
        $lineId = $cart->add($item->item_id, $size->size_id, [], 1, null);

        $this->actingAs($customer, 'customer')->delete("/cart/lines/{$lineId}");

        $this->assertEmpty(app(CartService::class)->lines());
    }
}
