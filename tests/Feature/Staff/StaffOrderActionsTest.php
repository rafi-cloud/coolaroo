<?php

namespace Tests\Feature\Staff;

use App\Enums\Destination;
use App\Enums\PaymentAttemptStatus;
use App\Models\AuditLog;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Payment;
use App\Models\RestaurantTable;
use App\Models\Role;
use App\Models\Staff;
use App\Services\CheckoutService;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class StaffOrderActionsTest extends TestCase
{
    use RefreshDatabase;

    private function pendingOrder(): Order
    {
        $item = MenuItem::factory()->create(['category_id' => MenuCategory::factory(), 'destination' => Destination::Kitchen]);
        $size = $item->sizes()->create(['size_name' => 'Regular', 'price' => 10]);
        $table = RestaurantTable::factory()->create();

        $result = app(CheckoutService::class)->checkout($table->table_id, null, [
            ['item_id' => $item->item_id, 'size_id' => $size->size_id, 'quantity' => 1, 'special_request' => null, 'add_on_option_ids' => []],
        ], (string) Str::uuid());

        return $result['order']->fresh();
    }

    private function staffWithRole(string $role): Staff
    {
        return Staff::factory()->create(['role_id' => Role::factory()->{$role}()->create()->role_id]);
    }

    public function test_waitstaff_cancels_an_unpaid_order_with_a_reason(): void
    {
        $order = $this->pendingOrder();
        $payment = Payment::factory()->create(['order_id' => $order->order_id, 'amount' => $order->total_amount]);

        $this->actingAs($this->staffWithRole('waitstaff'), 'staff')
            ->post(route('staff.orders.cancel', $order), ['reason' => 'Customer left'])
            ->assertRedirect();

        $this->assertSame('cancelled', $order->fresh()->status->value);
        $this->assertSame(PaymentAttemptStatus::Expired, $payment->fresh()->status);
        $this->assertDatabaseHas('audit_log', ['action_type' => 'order_cancelled', 'entity_id' => $order->order_id]);
    }

    public function test_a_paid_order_cannot_be_cancelled_by_staff(): void
    {
        $order = $this->pendingOrder();
        $payment = Payment::factory()->create(['order_id' => $order->order_id, 'amount' => $order->total_amount]);
        app(PaymentService::class)->markPaid($payment);

        $this->actingAs($this->staffWithRole('waitstaff'), 'staff')
            ->post(route('staff.orders.cancel', $order->fresh()), ['reason' => 'Changed mind'])
            ->assertSessionHasErrors('order');

        $this->assertSame('paid', $order->fresh()->status->value);
    }

    public function test_cancelling_without_a_reason_fails_validation(): void
    {
        $order = $this->pendingOrder();

        $this->actingAs($this->staffWithRole('waitstaff'), 'staff')
            ->post(route('staff.orders.cancel', $order), [])
            ->assertSessionHasErrors('reason');

        $this->assertSame('pending_payment', $order->fresh()->status->value);
    }

    public function test_kitchen_staff_cannot_cancel_an_order(): void
    {
        $order = $this->pendingOrder();

        $this->actingAs($this->staffWithRole('kitchen'), 'staff')
            ->post(route('staff.orders.cancel', $order), ['reason' => 'Nope'])
            ->assertForbidden();
    }

    public function test_kitchen_staff_resolve_a_stock_conflict_and_clear_the_flag(): void
    {
        $order = $this->pendingOrder();
        $order->forceFill(['has_stock_conflict' => true])->save();

        $this->actingAs($this->staffWithRole('kitchen'), 'staff')
            ->post(route('staff.orders.stock-conflict.resolve', $order), ['resolution' => 'will_make_it'])
            ->assertRedirect();

        $this->assertFalse($order->fresh()->has_stock_conflict);
        $this->assertSame('will_make_it', AuditLog::where('action_type', 'stock_conflict_resolved')->firstOrFail()->details['reason']);
    }

    public function test_waitstaff_cannot_resolve_a_stock_conflict(): void
    {
        $order = $this->pendingOrder();
        $order->forceFill(['has_stock_conflict' => true])->save();

        $this->actingAs($this->staffWithRole('waitstaff'), 'staff')
            ->post(route('staff.orders.stock-conflict.resolve', $order), ['resolution' => 'will_make_it'])
            ->assertForbidden();
    }
}
