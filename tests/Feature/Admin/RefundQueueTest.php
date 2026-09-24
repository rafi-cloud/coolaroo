<?php

namespace Tests\Feature\Admin;

use App\Enums\Destination;
use App\Enums\PaymentMethod;
use App\Enums\RefundStatus;
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
use App\Services\RefundService;
use App\Services\StripeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Stripe\Refund as StripeRefund;
use Tests\TestCase;

class RefundQueueTest extends TestCase
{
    use RefreshDatabase;

    private MenuItem $menuItem;

    private function paidOrder(int $quantity = 2, ?int $dailyLimit = null): Order
    {
        $this->menuItem = MenuItem::factory()->create([
            'category_id' => MenuCategory::factory(),
            'destination' => Destination::Kitchen,
            'daily_limit' => $dailyLimit,
        ]);
        $size = $this->menuItem->sizes()->create(['size_name' => 'Regular', 'price' => 10]);
        $table = RestaurantTable::factory()->create();

        $result = app(CheckoutService::class)->checkout($table->table_id, null, [
            ['item_id' => $this->menuItem->item_id, 'size_id' => $size->size_id, 'quantity' => $quantity, 'special_request' => null, 'add_on_option_ids' => []],
        ], (string) Str::uuid());

        $order = $result['order'];
        $payment = Payment::factory()->create([
            'order_id' => $order->order_id,
            'amount' => $order->total_amount,
            'method' => PaymentMethod::Stripe,
            'stripe_session_id' => 'cs_test_'.Str::random(10),
        ]);
        app(PaymentService::class)->markPaid($payment);

        return $order->fresh();
    }

    private function requestedRefund(Order $order, int $quantity = 1): Refund
    {
        $staff = Staff::factory()->create(['role_id' => Role::factory()->waitstaff()->create()->role_id]);

        return app(RefundService::class)->request($order->items->first(), $quantity, 'Cold food', $staff);
    }

    private function admin(): Staff
    {
        return Staff::factory()->create(['role_id' => Role::factory()->admin()->create()->role_id]);
    }

    public function test_admin_approves_a_stripe_partial_refund_without_returning_stock(): void
    {
        $order = $this->paidOrder(2, dailyLimit: 20);
        $refund = $this->requestedRefund($order, 1);
        $soldBefore = $this->menuItem->fresh()->sold_today;

        $this->mock(StripeService::class, function ($mock) {
            $mock->shouldReceive('createRefund')->once()->andReturn(
                StripeRefund::constructFrom(['id' => 're_test_1', 'status' => 'succeeded']),
            );
        });

        $this->actingAs($this->admin(), 'staff')
            ->patch(route('admin.refunds.approve', $refund), ['method' => 'stripe'])
            ->assertRedirect();

        $refund->refresh();
        $this->assertSame(RefundStatus::Completed, $refund->status);
        $this->assertSame('re_test_1', $refund->provider_refund_id);
        $this->assertSame('partially_refunded', $order->fresh()->payment_status->value);
        $this->assertSame(1, $order->items->first()->fresh()->refunded_qty);
        $this->assertSame($soldBefore, $this->menuItem->fresh()->sold_today);
    }

    public function test_a_manual_refund_completes_with_its_reference(): void
    {
        $order = $this->paidOrder(1);
        $refund = $this->requestedRefund($order, 1);

        $this->actingAs($this->admin(), 'staff')
            ->patch(route('admin.refunds.approve', $refund), [
                'method' => 'manual',
                'manual_reference' => 'BANK-99213',
            ])
            ->assertRedirect();

        $refund->refresh();
        $this->assertSame(RefundStatus::Completed, $refund->status);
        $this->assertSame('BANK-99213', $refund->manual_reference);
        $this->assertSame('refunded', $order->fresh()->payment_status->value);
        $this->assertSame('cancelled', $order->fresh()->status->value);
    }

    public function test_return_to_stock_reduces_sold_today_only_when_ticked(): void
    {
        $order = $this->paidOrder(2, dailyLimit: 20);
        $refund = $this->requestedRefund($order, 2);
        $soldBefore = $this->menuItem->fresh()->sold_today;

        $this->actingAs($this->admin(), 'staff')
            ->patch(route('admin.refunds.approve', $refund), [
                'method' => 'cash',
                'return_to_stock' => '1',
            ])
            ->assertRedirect();

        $this->assertSame($soldBefore - 2, $this->menuItem->fresh()->sold_today);
    }

    public function test_a_manual_refund_without_a_reference_is_rejected(): void
    {
        $refund = $this->requestedRefund($this->paidOrder(1), 1);

        $this->actingAs($this->admin(), 'staff')
            ->patch(route('admin.refunds.approve', $refund), ['method' => 'manual'])
            ->assertSessionHasErrors('manual_reference');

        $this->assertSame(RefundStatus::Requested, $refund->fresh()->status);
    }

    public function test_non_admin_staff_cannot_reach_the_refund_queue(): void
    {
        $waitstaff = Staff::factory()->create(['role_id' => Role::factory()->waitstaff()->create()->role_id]);

        $this->actingAs($waitstaff, 'staff')
            ->get(route('admin.refunds.index'))
            ->assertForbidden();
    }
}
