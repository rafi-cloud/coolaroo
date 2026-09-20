<?php

namespace Tests\Feature\Customer;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Customer;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Payment;
use App\Models\RestaurantTable;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MyOrdersTest extends TestCase
{
    use RefreshDatabase;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SettingSeeder::class);

        $this->customer = Customer::factory()->create([
            'email_verified_at' => now(),
        ]);
    }

    public function test_guest_is_redirected_to_customer_login(): void
    {
        $response = $this->get(route('orders.index'));

        $response->assertRedirect(route('customer.login'));
    }

    public function test_customer_can_view_empty_orders_page(): void
    {
        $response = $this->actingAs($this->customer, 'customer')
            ->get(route('orders.index'));

        $response->assertOk()
            ->assertSee('data-testid="orders-page"', false)
            ->assertSee('data-testid="no-orders-msg"', false)
            ->assertSee('No orders placed yet');
    }

    public function test_customer_can_view_their_orders(): void
    {
        $table = RestaurantTable::factory()->create(['table_number' => 12]);

        $order = Order::factory()->create([
            'customer_id' => $this->customer->customer_id,
            'table_id' => $table->table_id,
            'order_number' => 'ORD-1001',
            'status' => OrderStatus::Paid,
            'payment_status' => PaymentStatus::Paid,
            'total_amount' => 45.50,
        ]);

        Payment::factory()->create([
            'order_id' => $order->order_id,
            'method' => PaymentMethod::Stripe,
            'amount' => 45.50,
        ]);

        $menuItem = MenuItem::factory()->create([
            'category_id' => MenuCategory::factory(),
            'item_name' => 'Chicken Parma',
        ]);
        $size = $menuItem->sizes()->create(['size_name' => 'Regular', 'price' => 22.75]);

        $order->items()->create([
            'line_no' => 1,
            'item_id' => $menuItem->item_id,
            'size_id' => $size->size_id,
            'item_name' => $menuItem->item_name,
            'size_name' => $size->size_name,
            'destination' => $menuItem->destination->value,
            'quantity' => 2,
            'original_unit_price' => 22.75,
            'unit_price' => 22.75,
            'line_total' => 45.50,
        ]);

        $response = $this->actingAs($this->customer, 'customer')
            ->get(route('orders.index'));

        $response->assertOk()
            ->assertSee('ORD-1001')
            ->assertSee('Table 12')
            ->assertSee('Chicken Parma')
            ->assertSee('$45.50')
            ->assertSee(route('orders.show', $order))
            ->assertSee(route('orders.receipt', $order));
    }

    public function test_customer_does_not_see_other_customers_orders(): void
    {
        $otherCustomer = Customer::factory()->create();

        $otherOrder = Order::factory()->create([
            'customer_id' => $otherCustomer->customer_id,
            'order_number' => 'ORD-OTHER-999',
        ]);

        $response = $this->actingAs($this->customer, 'customer')
            ->get(route('orders.index'));

        $response->assertOk()
            ->assertDontSee('ORD-OTHER-999');
    }
}
