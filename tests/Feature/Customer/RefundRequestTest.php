<?php

namespace Tests\Feature\Customer;

use App\Enums\Destination;
use App\Models\Customer;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\RestaurantTable;
use App\Services\CheckoutService;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class RefundRequestTest extends TestCase
{
    use RefreshDatabase;

    private function paidOrderFor(Customer $customer, int $quantity = 2): Order
    {
        $item = MenuItem::factory()->create(['category_id' => MenuCategory::factory(), 'destination' => Destination::Kitchen]);
        $size = $item->sizes()->create(['size_name' => 'Regular', 'price' => 10]);
        $table = RestaurantTable::factory()->create();

        $result = app(CheckoutService::class)->checkout($table->table_id, $customer->customer_id, [
            ['item_id' => $item->item_id, 'size_id' => $size->size_id, 'quantity' => $quantity, 'special_request' => null, 'add_on_option_ids' => []],
        ], (string) Str::uuid());

        $payment = Payment::factory()->create(['order_id' => $result['order']->order_id, 'amount' => $result['order']->total_amount]);
        app(PaymentService::class)->markPaid($payment);

        return $result['order']->fresh();
    }

    public function test_a_customer_can_request_a_refund_on_their_own_paid_order(): void
    {
        $customer = Customer::factory()->create();
        $order = $this->paidOrderFor($customer);

        $this->actingAs($customer, 'customer')
            ->post(route('orders.refund-requests.store', $order), [
                'order_item_id' => $order->items->first()->order_item_id,
                'quantity' => 1,
                'reason' => 'The parma arrived cold',
            ])
            ->assertRedirect(route('orders.index'))
            ->assertSessionHas('status', 'refund-requested');

        $refund = Refund::where('order_id', $order->order_id)->firstOrFail();

        $this->assertSame($customer->customer_id, $refund->requested_by_customer_id);
        $this->assertNull($refund->requested_by_staff_id);
        $this->assertSame('The parma arrived cold', $refund->reason);
        $this->assertSame('requested', $refund->status->value);
    }

    public function test_my_orders_carries_the_form_and_the_tracker_only_links_to_it(): void
    {
        $customer = Customer::factory()->create();
        $order = $this->paidOrderFor($customer);

        $this->actingAs($customer, 'customer')
            ->get(route('orders.index'))
            ->assertOk()
            ->assertSee('refund-order-'.$order->order_id, false)
            ->assertSee('refund-request-form', false);

        $this->actingAs($customer, 'customer')
            ->get(route('orders.show', $order))
            ->assertOk()
            ->assertSee('refund-request-link', false)
            ->assertDontSee('refund-request-form', false);
    }

    public function test_a_raised_refund_is_trackable_from_my_orders(): void
    {
        $customer = Customer::factory()->create();
        $order = $this->paidOrderFor($customer);

        $this->actingAs($customer, 'customer')->post(route('orders.refund-requests.store', $order), [
            'order_item_id' => $order->items->first()->order_item_id,
            'quantity' => 1,
            'reason' => 'The parma arrived cold',
        ]);

        $refund = Refund::where('order_id', $order->order_id)->firstOrFail();

        $this->actingAs($customer, 'customer')
            ->get(route('orders.index'))
            ->assertOk()
            ->assertSee('refund-status-'.$refund->refund_id, false)
            ->assertSee('Requested', false);
    }

    public function test_a_customer_cannot_request_a_refund_on_someone_elses_order(): void
    {
        $owner = Customer::factory()->create();
        $order = $this->paidOrderFor($owner);

        $this->actingAs(Customer::factory()->create(), 'customer')
            ->post(route('orders.refund-requests.store', $order), [
                'order_item_id' => $order->items->first()->order_item_id,
                'quantity' => 1,
                'reason' => 'Not mine',
            ])
            ->assertForbidden();

        $this->assertSame(0, Refund::count());
    }

    public function test_the_window_closes_a_day_after_payment(): void
    {
        $customer = Customer::factory()->create();
        $order = $this->paidOrderFor($customer);
        $order->forceFill(['paid_at' => now()->subHours(25)])->save();

        $this->actingAs($customer, 'customer')
            ->get(route('orders.index'))
            ->assertOk()
            ->assertDontSee('refund-request-form', false);

        $this->actingAs($customer, 'customer')
            ->from(route('orders.show', $order))
            ->post(route('orders.refund-requests.store', $order), [
                'order_item_id' => $order->items->first()->order_item_id,
                'quantity' => 1,
                'reason' => 'Too late',
            ])
            ->assertSessionHasErrors('order');

        $this->assertSame(0, Refund::count());
    }
}
