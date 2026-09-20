<?php

namespace Tests\Feature\Customer;

use App\Models\Customer;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\RestaurantTable;
use App\Models\Setting;
use App\Services\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckoutQrPauseTest extends TestCase
{
    use RefreshDatabase;

    private function cartReadyToCheckout(): void
    {
        $table = RestaurantTable::factory()->create();
        session(['table_id' => $table->table_id]);

        $item = MenuItem::factory()->create(['category_id' => MenuCategory::factory()]);
        $size = $item->sizes()->create(['size_name' => 'Regular', 'price' => 12]);

        app(CartService::class)->add($item->item_id, $size->size_id, [], 1, null);
    }

    public function test_checkout_is_blocked_while_qr_ordering_is_paused(): void
    {
        $customer = Customer::factory()->create();
        $this->cartReadyToCheckout();
        Setting::updateOrCreate(['setting_key' => 'qr_ordering_enabled'], ['setting_value' => '0', 'value_type' => 'bool']);

        $this->actingAs($customer, 'customer')
            ->post('/checkout')
            ->assertSessionHas('error');

        $this->assertSame(0, Order::count());
    }

    public function test_checkout_succeeds_normally_when_qr_ordering_is_enabled(): void
    {
        $customer = Customer::factory()->create();
        $this->cartReadyToCheckout();

        $this->actingAs($customer, 'customer')
            ->post('/checkout')
            ->assertRedirect(route('cart.index'));

        $this->assertSame(1, Order::count());
    }
}
