<?php

namespace Tests\Feature\Customer;

use App\Models\Customer;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\RestaurantTable;
use App\Services\ReceiptService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReceiptTest extends TestCase
{
    use RefreshDatabase;

    private function paidOrderFor(Customer $customer): Order
    {
        $item = MenuItem::factory()->create(['category_id' => MenuCategory::factory()]);
        $size = $item->sizes()->create(['size_name' => 'Regular', 'price' => 12]);

        $order = Order::create([
            'table_id' => RestaurantTable::factory()->create()->table_id,
            'customer_id' => $customer->customer_id,
            'order_number' => '20260920-'.random_int(100, 999),
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
            'original_unit_price' => 15,
            'unit_price' => 12,
            'line_total' => 12,
        ]);

        return $order->fresh();
    }

    public function test_a_customer_can_download_their_own_receipt(): void
    {
        $customer = Customer::factory()->create();
        $order = $this->paidOrderFor($customer);

        $this->actingAs($customer, 'customer')
            ->get(route('orders.receipt', $order))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_a_customer_cannot_download_someone_elses_receipt(): void
    {
        $owner = Customer::factory()->create();
        $order = $this->paidOrderFor($owner);
        $other = Customer::factory()->create();

        $this->actingAs($other, 'customer')
            ->get(route('orders.receipt', $order))
            ->assertForbidden();
    }

    public function test_a_line_charged_below_its_original_price_is_flagged_as_on_sale(): void
    {
        $customer = Customer::factory()->create();
        $order = $this->paidOrderFor($customer);

        $data = app(ReceiptService::class)->data($order);

        $this->assertTrue($data['lines']->first()['onSale']);
    }
}
