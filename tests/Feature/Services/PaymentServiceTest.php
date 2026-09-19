<?php

namespace Tests\Feature\Services;

use App\Enums\Destination;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\Payment;
use App\Models\RestaurantTable;
use App\Models\Visit;
use App\Services\CheckoutService;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PaymentServiceTest extends TestCase
{
    use RefreshDatabase;

    private function orderFor(RestaurantTable $table, array $itemAttrs = []): Order
    {
        $item = MenuItem::factory()->create(['category_id' => MenuCategory::factory(), 'destination' => Destination::Kitchen, ...$itemAttrs]);
        $size = $item->sizes()->create(['size_name' => 'Regular', 'price' => 12]);

        $result = app(CheckoutService::class)->checkout($table->table_id, null, [
            ['item_id' => $item->item_id, 'size_id' => $size->size_id, 'quantity' => 1, 'special_request' => null, 'add_on_option_ids' => []],
        ], (string) Str::uuid());

        return $result['order'];
    }

    public function test_mark_paid_deducts_stock_and_marks_the_order_paid(): void
    {
        $table = RestaurantTable::factory()->create();
        $order = $this->orderFor($table, ['daily_limit' => 20, 'sold_today' => 0]);
        $payment = Payment::factory()->create(['order_id' => $order->order_id, 'amount' => $order->total_amount]);

        $paid = app(PaymentService::class)->markPaid($payment);

        $this->assertSame('paid', $paid->status->value);
        $this->assertSame('paid', $paid->payment_status->value);
        $this->assertSame(1, MenuItem::find($order->items->first()->item_id)->sold_today);
        $this->assertFalse($paid->has_stock_conflict);
    }

    public function test_mark_paid_flags_a_conflict_instead_of_blocking_payment(): void
    {
        $table = RestaurantTable::factory()->create();
        $order = $this->orderFor($table, ['daily_limit' => 20, 'sold_today' => 0]);
        $item = MenuItem::find($order->items->first()->item_id);
        // Simulates another order consuming the remaining stock between this
        // checkout and payment — passed the buffered check, fails the exact one.
        $item->update(['sold_today' => 20]);
        $payment = Payment::factory()->create(['order_id' => $order->order_id, 'amount' => $order->total_amount]);

        $paid = app(PaymentService::class)->markPaid($payment);

        $this->assertSame('paid', $paid->status->value);
        $this->assertTrue($paid->has_stock_conflict);
    }

    public function test_mark_paid_is_idempotent_on_an_already_succeeded_payment(): void
    {
        $table = RestaurantTable::factory()->create();
        $order = $this->orderFor($table, ['daily_limit' => 20, 'sold_today' => 0]);
        $payment = Payment::factory()->create(['order_id' => $order->order_id, 'amount' => $order->total_amount]);

        app(PaymentService::class)->markPaid($payment);
        app(PaymentService::class)->markPaid($payment->fresh());

        $item = MenuItem::find($order->items->first()->item_id);
        $this->assertSame(1, $item->sold_today);
        $this->assertSame(1, OrderStatusHistory::where('order_id', $order->order_id)->where('status', 'paid')->count());
    }

    public function test_mark_paid_opens_a_visit_and_occupies_an_available_table(): void
    {
        $table = RestaurantTable::factory()->create();
        $order = $this->orderFor($table);
        $payment = Payment::factory()->create(['order_id' => $order->order_id, 'amount' => $order->total_amount]);

        $paid = app(PaymentService::class)->markPaid($payment);

        $this->assertSame('occupied', $table->fresh()->status->value);
        $this->assertNotNull($paid->visit_id);
        $this->assertSame(1, Visit::where('table_id', $table->table_id)->count());
    }

    public function test_mark_paid_reuses_an_already_open_visit_on_an_occupied_table(): void
    {
        $table = RestaurantTable::factory()->create(['status' => 'occupied']);
        $existingVisit = Visit::create(['table_id' => $table->table_id, 'opened_at' => now()]);
        $order = $this->orderFor($table);
        $payment = Payment::factory()->create(['order_id' => $order->order_id, 'amount' => $order->total_amount]);

        $paid = app(PaymentService::class)->markPaid($payment);

        $this->assertSame($existingVisit->visit_id, $paid->visit_id);
        $this->assertSame(1, Visit::where('table_id', $table->table_id)->count());
    }

    public function test_mark_paid_sets_kitchen_eta_and_leaves_bar_eta_null(): void
    {
        $table = RestaurantTable::factory()->create();
        $order = $this->orderFor($table, ['prep_minutes' => 15]);
        $payment = Payment::factory()->create(['order_id' => $order->order_id, 'amount' => $order->total_amount]);

        $paid = app(PaymentService::class)->markPaid($payment);

        $this->assertNotNull($paid->kitchen_eta_at);
        $this->assertNull($paid->bar_eta_at);
    }

    public function test_mark_paid_records_status_history_and_audit_log(): void
    {
        $table = RestaurantTable::factory()->create();
        $order = $this->orderFor($table);
        $payment = Payment::factory()->create(['order_id' => $order->order_id, 'amount' => $order->total_amount]);

        app(PaymentService::class)->markPaid($payment);

        $this->assertDatabaseHas('order_status_history', ['order_id' => $order->order_id, 'status' => 'paid']);
        $this->assertDatabaseHas('audit_log', ['action_type' => 'payment_succeeded', 'entity_id' => $order->order_id]);
    }
}
