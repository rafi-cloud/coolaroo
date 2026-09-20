<?php

namespace Tests\Feature\Services;

use App\Enums\Destination;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\RestaurantTable;
use App\Services\CashPaymentService;
use App\Services\CheckoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CashPaymentServiceTest extends TestCase
{
    use RefreshDatabase;

    private function orderWithTotal(float $price): Order
    {
        $item = MenuItem::factory()->create(['category_id' => MenuCategory::factory(), 'destination' => Destination::Kitchen]);
        $size = $item->sizes()->create(['size_name' => 'Regular', 'price' => $price]);
        $table = RestaurantTable::factory()->create();

        $result = app(CheckoutService::class)->checkout($table->table_id, null, [
            ['item_id' => $item->item_id, 'size_id' => $size->size_id, 'quantity' => 1, 'special_request' => null, 'add_on_option_ids' => []],
        ], (string) Str::uuid());

        return $result['order'];
    }

    public function test_rounds_to_the_nearest_five_cents(): void
    {
        $order = $this->orderWithTotal(12.02);

        $this->assertSame(12.0, app(CashPaymentService::class)->roundedTotal($order));
    }

    public function test_amount_due_subtracts_the_adjustment_from_the_rounded_total(): void
    {
        $order = $this->orderWithTotal(20.00);

        $this->assertSame(15.0, app(CashPaymentService::class)->amountDue($order, 5.0));
    }

    public function test_change_given_is_received_minus_amount_due(): void
    {
        $this->assertSame(3.5, app(CashPaymentService::class)->changeGiven(16.5, 20.0));
    }

    public function test_flags_a_stock_warning_when_exact_stock_would_fail(): void
    {
        $order = $this->orderWithTotal(12.00);
        MenuItem::find($order->items->first()->item_id)->update(['daily_limit' => 1, 'sold_today' => 1]);

        $this->assertTrue(app(CashPaymentService::class)->wouldConflictStock($order));
    }
}
