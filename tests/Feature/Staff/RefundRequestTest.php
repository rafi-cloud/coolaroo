<?php

namespace Tests\Feature\Staff;

use App\Enums\Destination;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\RestaurantTable;
use App\Models\Role;
use App\Models\Staff;
use App\Services\CheckoutService;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class RefundRequestTest extends TestCase
{
    use RefreshDatabase;

    private function paidOrder(int $quantity = 2): Order
    {
        $item = MenuItem::factory()->create(['category_id' => MenuCategory::factory(), 'destination' => Destination::Kitchen]);
        $size = $item->sizes()->create(['size_name' => 'Regular', 'price' => 10]);
        $table = RestaurantTable::factory()->create();

        $result = app(CheckoutService::class)->checkout($table->table_id, null, [
            ['item_id' => $item->item_id, 'size_id' => $size->size_id, 'quantity' => $quantity, 'special_request' => null, 'add_on_option_ids' => []],
        ], (string) Str::uuid());

        $order = $result['order'];
        $payment = Payment::factory()->create(['order_id' => $order->order_id, 'amount' => $order->total_amount]);
        app(PaymentService::class)->markPaid($payment);

        return $order->fresh();
    }

    private function staffWithRole(string $role): Staff
    {
        return Staff::factory()->create(['role_id' => Role::factory()->{$role}()->create()->role_id]);
    }

    public function test_waitstaff_can_request_a_partial_refund(): void
    {
        $order = $this->paidOrder(2);
        $item = $order->items->first();

        $this->actingAs($this->staffWithRole('waitstaff'), 'staff')
            ->post(route('staff.orders.refund-requests.store', $order), [
                'order_item_id' => $item->order_item_id,
                'quantity' => 1,
                'reason' => 'Customer changed their mind',
            ])
            ->assertRedirect();

        $refund = Refund::where('order_item_id', $item->order_item_id)->firstOrFail();
        $this->assertSame(1, $refund->quantity);
        $this->assertEqualsWithDelta(10.0, (float) $refund->amount, 0.001);
        $this->assertSame('requested', $refund->status->value);
    }

    public function test_kitchen_staff_can_also_request_a_refund(): void
    {
        $order = $this->paidOrder(1);
        $item = $order->items->first();

        $this->actingAs($this->staffWithRole('kitchen'), 'staff')
            ->post(route('staff.orders.refund-requests.store', $order), [
                'order_item_id' => $item->order_item_id,
                'quantity' => 1,
                'reason' => 'Wrong item made',
            ])
            ->assertRedirect();

        $this->assertSame(1, Refund::where('order_item_id', $item->order_item_id)->count());
    }

    public function test_requesting_more_than_what_remains_is_rejected(): void
    {
        $order = $this->paidOrder(1);
        $item = $order->items->first();

        $this->actingAs($this->staffWithRole('waitstaff'), 'staff')
            ->post(route('staff.orders.refund-requests.store', $order), [
                'order_item_id' => $item->order_item_id,
                'quantity' => 2,
                'reason' => 'Too many',
            ])
            ->assertSessionHasErrors('quantity');

        $this->assertSame(0, Refund::count());
    }

    /** FR51 lists Waitstaff, so the form has to be on a screen they can open. */
    public function test_waitstaff_reach_the_refund_screen_from_the_floor(): void
    {
        $order = $this->paidOrder(2);
        $waiter = $this->staffWithRole('waitstaff');

        $this->actingAs($waiter, 'staff')
            ->get(route('staff.floor.index'))
            ->assertOk()
            ->assertSee('floor-table-order-link-'.$order->table_id, false)
            ->assertSee('floor-table-refund-link-'.$order->table_id, false);

        $this->actingAs($waiter, 'staff')
            ->get(route('staff.tables.refunds', $order->table_id))
            ->assertOk()
            ->assertSee('staff-refund-order-'.$order->order_id, false)
            ->assertSee('refund-request-submit', false);
    }

    /** The screen has to show what has already been asked for on each line. */
    public function test_the_refund_screen_tracks_a_request_already_raised(): void
    {
        $order = $this->paidOrder(2);
        $item = $order->items->first();
        $waiter = $this->staffWithRole('waitstaff');

        $this->actingAs($waiter, 'staff')
            ->post(route('staff.orders.refund-requests.store', $order), [
                'order_item_id' => $item->order_item_id,
                'quantity' => 1,
                'reason' => 'Steak was cold',
            ]);

        $refund = Refund::where('order_item_id', $item->order_item_id)->firstOrFail();

        $this->actingAs($waiter, 'staff')
            ->get(route('staff.tables.refunds', $order->table_id))
            ->assertOk()
            ->assertSee('refund-progress-'.$refund->refund_id, false)
            ->assertSee('Requested')
            ->assertDontSee('refund-none-'.$item->order_item_id, false);
    }

    /** A refund is asked for on the day, not a week later. */
    public function test_an_order_paid_more_than_a_day_ago_is_out_of_the_request_window(): void
    {
        $order = $this->paidOrder(2);
        $order->forceFill(['paid_at' => now()->subHours(25)])->save();

        $this->actingAs($this->staffWithRole('waitstaff'), 'staff')
            ->from(route('admin.orders.show', $order))
            ->post(route('staff.orders.refund-requests.store', $order), [
                'order_item_id' => $order->items->first()->order_item_id,
                'quantity' => 1,
                'reason' => 'Too late',
            ])
            ->assertSessionHasErrors('order');

        $this->assertSame(0, Refund::where('order_id', $order->order_id)->count());
    }

    public function test_a_guest_cannot_reach_the_staff_refund_route(): void
    {
        $order = $this->paidOrder(1);

        $this->post(route('staff.orders.refund-requests.store', $order), [
            'order_item_id' => $order->items->first()->order_item_id,
            'quantity' => 1,
            'reason' => 'x',
        ])->assertRedirect(route('staff.login'));
    }
}
