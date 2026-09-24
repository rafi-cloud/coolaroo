<?php

namespace Tests\Feature\Services;

use App\Models\MenuItem;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_qr_stock_check_passes_with_enough_buffer_room(): void
    {
        $item = MenuItem::factory()->create(['daily_limit' => 100, 'sold_today' => 50]);

        $this->assertTrue(app(StockService::class)->hasQrStock($item, 2));
    }

    public function test_qr_stock_check_fails_when_remaining_is_below_the_buffer(): void
    {
        $item = MenuItem::factory()->create(['daily_limit' => 100, 'sold_today' => 97]);

        $this->assertFalse(app(StockService::class)->hasQrStock($item, 1));
    }

    public function test_an_item_with_no_daily_limit_always_has_stock(): void
    {
        $item = MenuItem::factory()->create(['daily_limit' => null, 'sold_today' => 999]);

        $this->assertTrue(app(StockService::class)->hasQrStock($item, 1000));
        $this->assertFalse(app(StockService::class)->isSoldOutForQr($item));
    }

    public function test_deduct_succeeds_and_increments_sold_today_when_within_limit(): void
    {
        $item = MenuItem::factory()->create(['daily_limit' => 10, 'sold_today' => 5]);

        $result = app(StockService::class)->deduct($item, 3);

        $this->assertTrue($result);
        $this->assertSame(8, $item->fresh()->sold_today);
    }

    public function test_deduct_returns_false_and_leaves_sold_today_unchanged_when_it_would_exceed_the_limit(): void
    {
        $item = MenuItem::factory()->create(['daily_limit' => 10, 'sold_today' => 9]);

        $result = app(StockService::class)->deduct($item, 5);

        $this->assertFalse($result);
        $this->assertSame(9, $item->fresh()->sold_today);
    }

    public function test_return_to_stock_decrements_sold_today(): void
    {
        $item = MenuItem::factory()->create(['daily_limit' => 10, 'sold_today' => 5]);

        app(StockService::class)->returnToStock($item, 2);

        $this->assertSame(3, $item->fresh()->sold_today);
    }

    public function test_reset_daily_counters_zeroes_every_item(): void
    {
        $a = MenuItem::factory()->create(['sold_today' => 7]);
        $b = MenuItem::factory()->create(['sold_today' => 12]);

        app(StockService::class)->resetDailyCounters();

        $this->assertSame(0, $a->fresh()->sold_today);
        $this->assertSame(0, $b->fresh()->sold_today);
    }
}
