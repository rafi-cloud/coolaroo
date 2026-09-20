<?php

namespace Tests\Feature\Customer;

use App\Enums\PaymentAttemptStatus;
use App\Models\Customer;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Payment;
use App\Models\RestaurantTable;
use App\Services\StripeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Stripe\Checkout\Session;
use Tests\TestCase;

class PaymentTest extends TestCase
{
    use RefreshDatabase;

    private function pendingOrderFor(Customer $customer): Order
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

    public function test_starting_a_stripe_payment_creates_a_pending_payment_row_and_redirects_to_stripe(): void
    {
        $customer = Customer::factory()->create();
        $order = $this->pendingOrderFor($customer);

        $fakeSession = Session::constructFrom(['id' => 'cs_test_123', 'url' => 'https://checkout.stripe.com/pay/cs_test_123']);

        $this->mock(StripeService::class, function ($mock) use ($fakeSession) {
            $mock->shouldReceive('createCheckoutSession')->once()->andReturn($fakeSession);
        });

        $this->actingAs($customer, 'customer')
            ->post(route('orders.pay.stripe', $order))
            ->assertRedirect('https://checkout.stripe.com/pay/cs_test_123');

        $payment = Payment::where('order_id', $order->order_id)->firstOrFail();
        $this->assertSame('cs_test_123', $payment->stripe_session_id);
        $this->assertSame(PaymentAttemptStatus::Pending, $payment->status);
    }

    public function test_the_return_url_marks_the_order_paid_when_stripe_confirms_payment(): void
    {
        $customer = Customer::factory()->create();
        $order = $this->pendingOrderFor($customer);
        Payment::create(['order_id' => $order->order_id, 'method' => 'stripe', 'amount' => 12, 'stripe_session_id' => 'cs_test_456']);

        $fakeSession = Session::constructFrom(['id' => 'cs_test_456', 'payment_status' => 'paid']);

        $this->mock(StripeService::class, function ($mock) use ($fakeSession) {
            $mock->shouldReceive('retrieveSession')->with('cs_test_456')->once()->andReturn($fakeSession);
        });

        $this->actingAs($customer, 'customer')
            ->get(route('payment.success', ['session_id' => 'cs_test_456']))
            ->assertRedirect(route('orders.show', $order));

        $this->assertSame('paid', $order->fresh()->status->value);
    }

    public function test_the_return_url_leaves_the_order_pending_when_stripe_has_not_confirmed_payment(): void
    {
        $customer = Customer::factory()->create();
        $order = $this->pendingOrderFor($customer);
        Payment::create(['order_id' => $order->order_id, 'method' => 'stripe', 'amount' => 12, 'stripe_session_id' => 'cs_test_789']);

        $fakeSession = Session::constructFrom(['id' => 'cs_test_789', 'payment_status' => 'unpaid']);

        $this->mock(StripeService::class, function ($mock) use ($fakeSession) {
            $mock->shouldReceive('retrieveSession')->once()->andReturn($fakeSession);
        });

        $this->actingAs($customer, 'customer')
            ->get(route('payment.success', ['session_id' => 'cs_test_789']))
            ->assertSessionHas('error');

        $this->assertSame('pending_payment', $order->fresh()->status->value);
    }

    public function test_a_customer_cannot_start_payment_on_someone_elses_order(): void
    {
        $owner = Customer::factory()->create();
        $order = $this->pendingOrderFor($owner);
        $other = Customer::factory()->create();

        $this->actingAs($other, 'customer')
            ->post(route('orders.pay.stripe', $order))
            ->assertForbidden();
    }

    public function test_requesting_cash_payment_creates_a_pending_cash_payment_and_keeps_the_order_pending(): void
    {
        $customer = Customer::factory()->create();
        $order = $this->pendingOrderFor($customer);

        $this->actingAs($customer, 'customer')
            ->post(route('orders.pay.cash', $order))
            ->assertRedirect(route('orders.show', $order));

        $payment = Payment::where('order_id', $order->order_id)->firstOrFail();
        $this->assertSame('cash', $payment->method->value);
        $this->assertSame('pending_payment', $order->fresh()->status->value);
    }
}
