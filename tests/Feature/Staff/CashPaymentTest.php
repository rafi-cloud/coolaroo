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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CashPaymentTest extends TestCase
{
    use RefreshDatabase;

    private function pendingOrderWithoutCashRequest(): Order
    {
        $item = MenuItem::factory()->create(['category_id' => MenuCategory::factory(), 'destination' => Destination::Kitchen]);
        $size = $item->sizes()->create(['size_name' => 'Regular', 'price' => 12]);
        $table = RestaurantTable::factory()->create();

        $result = app(CheckoutService::class)->checkout($table->table_id, null, [
            ['item_id' => $item->item_id, 'size_id' => $size->size_id, 'quantity' => 1, 'special_request' => null, 'add_on_option_ids' => []],
        ], (string) Str::uuid());

        return $result['order'];
    }

    private function pendingCashOrder(): Order
    {
        $item = MenuItem::factory()->create(['category_id' => MenuCategory::factory(), 'destination' => Destination::Kitchen]);
        $size = $item->sizes()->create(['size_name' => 'Regular', 'price' => 12]);
        $table = RestaurantTable::factory()->create();

        $result = app(CheckoutService::class)->checkout($table->table_id, null, [
            ['item_id' => $item->item_id, 'size_id' => $size->size_id, 'quantity' => 1, 'special_request' => null, 'add_on_option_ids' => []],
        ], (string) Str::uuid());

        $order = $result['order'];
        Payment::create(['order_id' => $order->order_id, 'method' => 'cash', 'amount' => $order->total_amount]);

        return $order;
    }

    public function test_a_waiter_can_take_cash_for_an_order_the_customer_never_flagged_as_cash(): void
    {
        $order = $this->pendingOrderWithoutCashRequest();
        $waiter = Staff::factory()->create(['role_id' => Role::factory()->waitstaff()->create()->role_id]);

        $this->assertSame(0, $order->payments()->count());

        $this->actingAs($waiter, 'staff')
            ->post(route('staff.orders.cash.store', $order), ['amount_received' => 50])
            ->assertRedirect();

        $this->assertSame('paid', $order->fresh()->status->value);
        $this->assertSame('succeeded', $order->payments()->latest('payment_id')->first()->status->value);
    }

    public function test_taking_cash_twice_on_the_same_order_is_refused(): void
    {
        $order = $this->pendingCashOrder();
        $waiter = Staff::factory()->create(['role_id' => Role::factory()->waitstaff()->create()->role_id]);

        $this->actingAs($waiter, 'staff')
            ->post(route('staff.orders.cash.store', $order), ['amount_received' => 50])
            ->assertRedirect();

        $this->actingAs($waiter, 'staff')
            ->post(route('staff.orders.cash.store', $order), ['amount_received' => 50])
            ->assertNotFound();

        $this->assertSame(1, $order->payments()->where('status', 'succeeded')->count());
    }

    public function test_the_floor_offers_a_way_through_to_take_a_waiting_cash_payment(): void
    {
        $order = $this->pendingCashOrder();
        $waiter = Staff::factory()->create(['role_id' => Role::factory()->waitstaff()->create()->role_id]);
        $payment = Payment::where('order_id', $order->order_id)->firstOrFail();

        $this->actingAs($waiter, 'staff')
            ->get(route('staff.floor.index'))
            ->assertOk()
            ->assertSee($order->order_number)
            ->assertSee(route('staff.tables.order', $order->table_id), false)
            ->assertSee('data-testid="floor-cash-settle-'.$payment->payment_id.'"', false);
    }

    public function test_the_live_floor_payload_carries_somewhere_to_settle_each_cash_request(): void
    {
        $order = $this->pendingCashOrder();
        $waiter = Staff::factory()->create(['role_id' => Role::factory()->waitstaff()->create()->role_id]);

        $this->actingAs($waiter, 'staff')
            ->getJson(route('staff.floor.state'))
            ->assertOk()
            ->assertJsonPath('cash_waiting.0.order_number', $order->order_number)
            ->assertJsonPath('cash_waiting.0.settle_url', route('staff.tables.order', $order->table_id));
    }

    public function test_waitstaff_can_record_a_cash_payment_and_it_marks_the_order_paid(): void
    {
        $waiter = Staff::factory()->create(['role_id' => Role::factory()->waitstaff()->create()->role_id]);
        $order = $this->pendingCashOrder();

        $this->actingAs($waiter, 'staff')
            ->post(route('staff.orders.cash.store', $order), ['amount_received' => 20])
            ->assertRedirect();

        $this->assertSame('paid', $order->fresh()->status->value);

        $payment = Payment::where('order_id', $order->order_id)->firstOrFail();
        $this->assertNotNull($payment->change_given);
        $this->assertSame($waiter->staff_id, $payment->recorded_by_staff_id);
    }

    public function test_kitchen_staff_cannot_record_a_cash_payment(): void
    {
        $staff = Staff::factory()->create(['role_id' => Role::factory()->kitchen()->create()->role_id]);
        $order = $this->pendingCashOrder();

        $this->actingAs($staff, 'staff')
            ->post(route('staff.orders.cash.store', $order), ['amount_received' => 20])
            ->assertForbidden();
    }

    public function test_an_adjustment_without_a_category_or_note_is_rejected(): void
    {
        $waiter = Staff::factory()->create(['role_id' => Role::factory()->waitstaff()->create()->role_id]);
        $order = $this->pendingCashOrder();

        $this->actingAs($waiter, 'staff')
            ->post(route('staff.orders.cash.store', $order), [
                'amount_received' => 20,
                'adjustment_amount' => 5,
            ])
            ->assertSessionHasErrors(['adjustment_category', 'adjustment_note']);

        $this->assertSame('pending_payment', $order->fresh()->status->value);
    }
}
