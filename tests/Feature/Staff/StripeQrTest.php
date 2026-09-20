<?php

namespace Tests\Feature\Staff;

use App\Enums\Destination;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Payment;
use App\Models\RestaurantTable;
use App\Models\Role;
use App\Models\Staff;
use App\Services\CheckoutService;
use App\Services\StripeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Stripe\Checkout\Session;
use Tests\TestCase;

class StripeQrTest extends TestCase
{
    use RefreshDatabase;

    private function pendingOrder(): Order
    {
        $item = MenuItem::factory()->create(['category_id' => MenuCategory::factory(), 'destination' => Destination::Kitchen]);
        $size = $item->sizes()->create(['size_name' => 'Regular', 'price' => 12]);
        $table = RestaurantTable::factory()->create();

        $result = app(CheckoutService::class)->checkout($table->table_id, null, [
            ['item_id' => $item->item_id, 'size_id' => $size->size_id, 'quantity' => 1, 'special_request' => null, 'add_on_option_ids' => []],
        ], (string) Str::uuid());

        return $result['order'];
    }

    private function waiter(): Staff
    {
        return Staff::factory()->create(['role_id' => Role::factory()->waitstaff()->create()->role_id]);
    }

    public function test_waitstaff_can_generate_a_stripe_qr_for_a_pending_order(): void
    {
        $order = $this->pendingOrder();
        $fakeSession = Session::constructFrom(['id' => 'cs_test_qr', 'url' => 'https://checkout.stripe.com/pay/cs_test_qr']);

        $this->mock(StripeService::class, function ($mock) use ($fakeSession) {
            $mock->shouldReceive('createCheckoutSession')->once()->andReturn($fakeSession);
        });

        $this->actingAs($this->waiter(), 'staff')
            ->get(route('staff.orders.stripe-qr', $order))
            ->assertOk()
            ->assertSee('order #'.$order->order_number, false);

        $this->assertSame('cs_test_qr', Payment::where('order_id', $order->order_id)->firstOrFail()->stripe_session_id);
    }

    public function test_kitchen_staff_cannot_generate_a_stripe_qr(): void
    {
        $order = $this->pendingOrder();
        $staff = Staff::factory()->create(['role_id' => Role::factory()->kitchen()->create()->role_id]);

        $this->actingAs($staff, 'staff')
            ->get(route('staff.orders.stripe-qr', $order))
            ->assertForbidden();
    }

    public function test_checking_payment_status_marks_the_order_paid_when_stripe_confirms(): void
    {
        $order = $this->pendingOrder();
        Payment::create(['order_id' => $order->order_id, 'method' => 'stripe', 'amount' => 12, 'stripe_session_id' => 'cs_test_check']);
        $fakeSession = Session::constructFrom(['id' => 'cs_test_check', 'payment_status' => 'paid']);

        $this->mock(StripeService::class, function ($mock) use ($fakeSession) {
            $mock->shouldReceive('retrieveSession')->once()->andReturn($fakeSession);
        });

        $this->actingAs($this->waiter(), 'staff')
            ->post(route('staff.orders.payment-check', $order))
            ->assertSessionHas('status', 'order-paid');

        $this->assertSame('paid', $order->fresh()->status->value);
    }
}
