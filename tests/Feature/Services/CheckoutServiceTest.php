<?php

namespace Tests\Feature\Services;

use App\Enums\TableStatus;
use App\Models\AddOnGroup;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\RestaurantTable;
use App\Models\Visit;
use App\Services\CheckoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CheckoutServiceTest extends TestCase
{
    use RefreshDatabase;

    private function itemWithSize(array $itemAttrs = []): array
    {
        $item = MenuItem::factory()->create(['category_id' => MenuCategory::factory(), ...$itemAttrs]);
        $size = $item->sizes()->create(['size_name' => 'Regular', 'price' => 12]);

        return [$item, $size];
    }

    private function tableId(): int
    {
        return RestaurantTable::factory()->create()->table_id;
    }

    public function test_checkout_creates_an_order_with_correct_total_and_gst(): void
    {
        [$item, $size] = $this->itemWithSize();

        $result = app(CheckoutService::class)->checkout($this->tableId(), null, [
            ['item_id' => $item->item_id, 'size_id' => $size->size_id, 'quantity' => 2, 'special_request' => null, 'add_on_option_ids' => []],
        ], (string) Str::uuid());

        $this->assertSame('24.00', (string) $result['order']->total_amount);
        $this->assertSame('2.18', (string) $result['order']->gst_amount);
        $this->assertSame(1, $result['order']->items()->count());
        $this->assertEmpty($result['removed']);
    }

    /** BR01: the table is taken when the order is placed, not when it is paid. */
    public function test_placing_an_order_occupies_the_table_and_opens_a_visit(): void
    {
        [$item, $size] = $this->itemWithSize();
        $table = RestaurantTable::factory()->create(['status' => TableStatus::Available]);

        $result = app(CheckoutService::class)->checkout($table->table_id, null, [
            ['item_id' => $item->item_id, 'size_id' => $size->size_id, 'quantity' => 1, 'special_request' => null, 'add_on_option_ids' => []],
        ], (string) Str::uuid());

        $this->assertSame(TableStatus::Occupied, $table->fresh()->status);

        $visit = Visit::where('table_id', $table->table_id)->whereNull('closed_at')->first();
        $this->assertNotNull($visit->opened_at);
        $this->assertSame($visit->visit_id, $result['order']->fresh()->visit_id);
    }

    public function test_checkout_is_idempotent_on_the_same_key(): void
    {
        [$item, $size] = $this->itemWithSize();
        $key = (string) Str::uuid();
        $lines = [['item_id' => $item->item_id, 'size_id' => $size->size_id, 'quantity' => 1, 'special_request' => null, 'add_on_option_ids' => []]];
        $tableId = $this->tableId();

        $first = app(CheckoutService::class)->checkout($tableId, null, $lines, $key);
        $second = app(CheckoutService::class)->checkout($tableId, null, $lines, $key);

        $this->assertSame($first['order']->order_id, $second['order']->order_id);
        $this->assertSame(1, Order::count());
    }

    public function test_an_empty_cart_is_rejected(): void
    {
        $this->expectException(ValidationException::class);

        app(CheckoutService::class)->checkout($this->tableId(), null, [], (string) Str::uuid());
    }

    public function test_a_deactivated_item_is_dropped_while_the_rest_of_the_order_still_succeeds(): void
    {
        [$goneItem, $goneSize] = $this->itemWithSize(['is_active' => false]);
        [$item, $size] = $this->itemWithSize();

        $result = app(CheckoutService::class)->checkout($this->tableId(), null, [
            ['item_id' => $goneItem->item_id, 'size_id' => $goneSize->size_id, 'quantity' => 1, 'special_request' => null, 'add_on_option_ids' => []],
            ['item_id' => $item->item_id, 'size_id' => $size->size_id, 'quantity' => 1, 'special_request' => null, 'add_on_option_ids' => []],
        ], (string) Str::uuid());

        $this->assertContains($goneItem->item_name, $result['removed']);
        $this->assertSame(1, $result['order']->items()->count());
    }

    public function test_checkout_fails_when_every_line_is_unavailable(): void
    {
        [$item, $size] = $this->itemWithSize(['is_active' => false]);

        $this->expectException(ValidationException::class);

        app(CheckoutService::class)->checkout($this->tableId(), null, [
            ['item_id' => $item->item_id, 'size_id' => $size->size_id, 'quantity' => 1, 'special_request' => null, 'add_on_option_ids' => []],
        ], (string) Str::uuid());
    }

    public function test_checkout_blocks_a_quantity_over_the_buffered_stock_limit(): void
    {
        [$item, $size] = $this->itemWithSize(['daily_limit' => 10, 'sold_today' => 8]);

        $this->expectException(ValidationException::class);

        app(CheckoutService::class)->checkout($this->tableId(), null, [
            ['item_id' => $item->item_id, 'size_id' => $size->size_id, 'quantity' => 1, 'special_request' => null, 'add_on_option_ids' => []],
        ], (string) Str::uuid());
    }

    public function test_order_item_snapshots_add_on_name_and_price(): void
    {
        [$item, $size] = $this->itemWithSize();
        $group = AddOnGroup::create(['item_id' => $item->item_id, 'group_name' => 'Extras', 'max_select' => 1]);
        $option = $group->options()->create(['option_name' => 'Extra cheese', 'price_delta' => 2, 'is_active' => true, 'is_available' => true]);

        $result = app(CheckoutService::class)->checkout($this->tableId(), null, [
            ['item_id' => $item->item_id, 'size_id' => $size->size_id, 'quantity' => 1, 'special_request' => 'No onion', 'add_on_option_ids' => [$option->option_id]],
        ], (string) Str::uuid());

        $line = $result['order']->items()->first();
        $this->assertSame('14.00', (string) $line->line_total);
        $this->assertSame('No onion', $line->special_request);
        $this->assertSame('Extra cheese', $line->selected_options[0]['name']);
    }
}
