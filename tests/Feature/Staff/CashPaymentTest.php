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
