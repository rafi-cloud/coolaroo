<?php

namespace Tests\Feature\Customer;

use App\Models\Customer;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\RestaurantTable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class OrderTest extends TestCase
{
    use RefreshDatabase;

    private function orderFor(Customer $customer): Order
    {
        $item = MenuItem::factory()->create(['category_id' => MenuCategory::factory()]);
        $size = $item->sizes()->create(['size_name' => 'Regular', 'price' => 12]);

        $order = Order::create([
            'table_id' => RestaurantTable::factory()->create()->table_id,
            'customer_id' => $customer->customer_id,
            'order_number' => '20260920-001',
            'idempotency_key' => (string) Str::uuid(),
            'total_amount' => 12,
            'gst_amount' => 1.09,
        ]);
        $order->forceFill(['status' => 'paid', 'payment_status' => 'paid'])->save();
        $order->update(['paid_at' => now()]);

        $order->items()->create([
            'line_no' => 1,
            'item_id' => $item->item_id,
            'size_id' => $size->size_id,
            'item_name' => $item->item_name,
            'size_name' => $size->size_name,
            'destination' => $item->destination->value,
            'quantity' => 1,
            'original_unit_price' => 12,
            'unit_price' => 12,
            'line_total' => 12,
        ]);

        return $order->fresh();
    }

    public function test_a_customer_can_view_their_own_order_timeline(): void
    {
        $customer = Customer::factory()->create();
        $order = $this->orderFor($customer);

        $this->actingAs($customer, 'customer')
            ->get(route('orders.show', $order))
            ->assertOk()
            ->assertSee('Order #'.$order->order_number)
            ->assertSee('Paid');
    }

    public function test_a_customer_cannot_view_someone_elses_order(): void
    {
        $owner = Customer::factory()->create();
        $order = $this->orderFor($owner);
        $other = Customer::factory()->create();

        $this->actingAs($other, 'customer')
            ->get(route('orders.show', $order))
            ->assertForbidden();
    }

    public function test_the_state_endpoint_reports_the_current_status(): void
    {
        $customer = Customer::factory()->create();
        $order = $this->orderFor($customer);

        $this->actingAs($customer, 'customer')
            ->getJson(route('orders.state', $order))
            ->assertOk()
            ->assertJson(['status' => 'paid', 'payment_status' => 'paid']);
    }
}
