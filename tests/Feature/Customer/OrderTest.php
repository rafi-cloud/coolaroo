<?php

namespace Tests\Feature\Customer;

use App\Enums\PaymentAttemptStatus;
use App\Enums\PaymentMethod;
use App\Models\Customer;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Payment;
use App\Models\RestaurantTable;
use App\Services\StripeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class OrderTest extends TestCase
{
    use RefreshDatabase;

    private function orderFor(Customer $customer, string $status = 'paid'): Order
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
        $order->forceFill([
            'status' => $status,
            'payment_status' => $status === 'pending_payment' ? 'unpaid' : 'paid',
        ])->save();

        if ($status !== 'pending_payment') {
            $order->update(['paid_at' => now()]);
        }

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

    public function test_a_served_order_offers_the_feedback_form_and_drops_the_payment_prompt(): void
    {
        $customer = Customer::factory()->create();
        $order = $this->orderFor($customer, 'served');

        $this->actingAs($customer, 'customer')
            ->get(route('orders.show', $order))
            ->assertOk()
            ->assertSee('feedback-form')
            ->assertDontSee('Complete Payment');
    }

    public function test_an_order_waiting_on_cash_does_not_push_the_card_payment(): void
    {
        $customer = Customer::factory()->create();
        $order = $this->orderFor($customer, 'pending_payment');
        Payment::create([
            'order_id' => $order->order_id,
            'method' => PaymentMethod::Cash,
            'amount' => $order->total_amount,
        ]);

        $this->actingAs($customer, 'customer')
            ->get(route('orders.show', $order))
            ->assertOk()
            ->assertSee('order-cash-waiting')
            ->assertDontSee('Complete Payment')
            ->assertDontSee('Check payment status');
    }

    public function test_a_station_eta_disappears_once_that_station_has_nothing_left_to_cook(): void
    {
        $customer = Customer::factory()->create();
        $order = $this->orderFor($customer, 'preparing');
        $order->forceFill(['kitchen_eta_at' => now()->addMinutes(10)])->save();

        $this->actingAs($customer, 'customer')
            ->get(route('orders.show', $order))
            ->assertSee('Kitchen: ready between');

        $order->items()->update(['status' => 'served']);
        $order->forceFill(['status' => 'served'])->save();

        $this->actingAs($customer, 'customer')
            ->get(route('orders.show', $order))
            ->assertDontSee('Kitchen: ready between');
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

    public function test_a_customer_can_cancel_their_own_pending_payment_order(): void
    {
        $customer = Customer::factory()->create();
        $order = $this->orderFor($customer, 'pending_payment');

        $this->actingAs($customer, 'customer')
            ->post(route('orders.cancel', $order))
            ->assertRedirect(route('orders.show', $order));

        $this->assertSame('cancelled', $order->fresh()->status->value);
        $this->assertNotNull($order->fresh()->cancelled_at);
    }

    public function test_cancelling_an_order_cancels_its_unstarted_lines(): void
    {
        $customer = Customer::factory()->create();
        $order = $this->orderFor($customer, 'pending_payment');

        $this->actingAs($customer, 'customer')
            ->post(route('orders.cancel', $order))
            ->assertRedirect(route('orders.show', $order));

        $this->assertSame(['cancelled'], $order->items()->pluck('status')->map->value->all());
    }

    public function test_a_customer_cannot_cancel_someone_elses_order(): void
    {
        $owner = Customer::factory()->create();
        $order = $this->orderFor($owner, 'pending_payment');
        $other = Customer::factory()->create();

        $this->actingAs($other, 'customer')
            ->post(route('orders.cancel', $order))
            ->assertForbidden();
    }

    public function test_a_customer_cannot_cancel_an_already_paid_order(): void
    {
        $customer = Customer::factory()->create();
        $order = $this->orderFor($customer, 'paid');

        $this->actingAs($customer, 'customer')
            ->post(route('orders.cancel', $order))
            ->assertForbidden();

        $this->assertSame('paid', $order->fresh()->status->value);
    }

    public function test_cancelling_an_order_expires_its_pending_stripe_session(): void
    {
        $customer = Customer::factory()->create();
        $order = $this->orderFor($customer, 'pending_payment');
        $payment = Payment::create([
            'order_id' => $order->order_id,
            'method' => 'stripe',
            'amount' => 12,
            'stripe_session_id' => 'cs_test_expire_me',
        ]);

        $this->mock(StripeService::class, function ($mock) {
            $mock->shouldReceive('expireSession')->with('cs_test_expire_me')->once();
        });

        $this->actingAs($customer, 'customer')
            ->post(route('orders.cancel', $order));

        $this->assertSame(PaymentAttemptStatus::Expired, $payment->fresh()->status);
    }
}
