<?php

namespace Tests\Feature\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentAttemptStatus;
use App\Enums\PaymentMethod;
use App\Enums\RefundMethod;
use App\Enums\RefundStatus;
use App\Models\Feedback;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\Setting;
use App\Models\Staff;
use App\Services\ReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportServiceTest extends TestCase
{
    use RefreshDatabase;

    private function paidOrder(float $amount, ?\DateTimeInterface $paidAt = null): Order
    {
        $order = Order::factory()->create(['total_amount' => $amount]);

        $order->forceFill([
            'status' => OrderStatus::Paid,
            'paid_at' => $paidAt ?? now(),
        ])->save();

        return $order;
    }

    /** Widget 1: today against the same weekday last week, not yesterday. */
    public function test_sales_today_compares_against_the_same_weekday_last_week(): void
    {
        $this->paidOrder(60.00);
        $this->paidOrder(40.00);
        $this->paidOrder(50.00, now()->subWeek());
        $this->paidOrder(999.00, now()->subDay());

        $sales = app(ReportService::class)->salesToday();

        $this->assertSame(100.00, $sales['amount']);
        $this->assertSame(50.00, $sales['previous']);
        $this->assertSame(100.0, $sales['change_pct']);
    }

    /** Widgets 2 and 3: unpaid orders are not sales. */
    public function test_order_count_and_average_ignore_unpaid_orders(): void
    {
        $this->paidOrder(60.00);
        $this->paidOrder(40.00);
        Order::factory()->create(['total_amount' => 500]);

        $service = app(ReportService::class);

        $this->assertSame(2, $service->ordersToday());
        $this->assertSame(50.00, $service->averageOrderValue());
    }

    /** Widget 4: the split comes from the payment rows, so rounding and adjustments count. */
    public function test_cash_versus_stripe_splits_todays_takings(): void
    {
        $cashOrder = $this->paidOrder(30.00);
        $stripeOrder = $this->paidOrder(70.00);

        Payment::factory()->create([
            'order_id' => $cashOrder->order_id,
            'method' => PaymentMethod::Cash,
            'amount' => 30.00,
            'status' => PaymentAttemptStatus::Succeeded,
            'paid_at' => now(),
        ]);

        Payment::factory()->create([
            'order_id' => $stripeOrder->order_id,
            'method' => PaymentMethod::Stripe,
            'amount' => 70.00,
            'status' => PaymentAttemptStatus::Succeeded,
            'paid_at' => now(),
        ]);

        $split = app(ReportService::class)->cashVsStripe();

        $this->assertSame(30.00, $split['cash']);
        $this->assertSame(70.00, $split['stripe']);
        $this->assertSame(30.0, $split['cash_pct']);
    }

    /** Widget 8: hidden feedback is excluded (FR78). */
    public function test_average_rating_excludes_hidden_feedback(): void
    {
        Feedback::factory()->create([
            'order_id' => $this->paidOrder(20.00)->order_id,
            'food_rating' => 5,
            'service_rating' => 5,
            'submitted_at' => now(),
        ]);

        Feedback::factory()->create([
            'order_id' => $this->paidOrder(20.00)->order_id,
            'food_rating' => 1,
            'service_rating' => 1,
            'is_hidden' => true,
            'hidden_reason' => 'Abusive',
            'submitted_at' => now(),
        ]);

        $rating = app(ReportService::class)->averageRating();

        $this->assertSame(5.0, $rating['food']);
        $this->assertSame(5.0, $rating['service']);
        $this->assertSame(1, $rating['count']);
    }

    /** Widget 13: revenue comes from the line snapshots (BR15). */
    public function test_top_items_today_ranks_by_quantity_from_line_snapshots(): void
    {
        $order = $this->paidOrder(100.00);
        $parma = MenuItem::factory()->create(['item_name' => 'Chicken Parma']);
        $salad = MenuItem::factory()->create(['item_name' => 'Garden Salad']);

        $this->lineFor($order, $parma, quantity: 3, lineTotal: 60.00, lineNo: 1);
        $this->lineFor($order, $salad, quantity: 1, lineTotal: 16.00, lineNo: 2);

        $top = app(ReportService::class)->topItemsToday();

        $this->assertSame('Chicken Parma', $top[0]['item_name']);
        $this->assertSame(3, $top[0]['quantity']);
        $this->assertSame(60.00, $top[0]['revenue']);
        $this->assertSame('Garden Salad', $top[1]['item_name']);
    }

    /** Widget 14: sold out by hand, or inside 2× the QR buffer (08.5, BR09). */
    public function test_low_stock_lists_sold_out_items_and_items_inside_twice_the_buffer(): void
    {
        Setting::updateOrCreate(['setting_key' => 'qr_stock_buffer_multiplier'], ['setting_value' => '5']);

        $soldOut = MenuItem::factory()->create(['item_name' => 'Off the menu', 'is_available' => false]);
        $low = MenuItem::factory()->create(['item_name' => 'Nearly gone', 'daily_limit' => 20, 'sold_today' => 12]);
        MenuItem::factory()->create(['item_name' => 'Plenty left', 'daily_limit' => 100, 'sold_today' => 1]);
        MenuItem::factory()->create(['item_name' => 'Unlimited']);

        $names = collect(app(ReportService::class)->lowStock())
            ->map(fn (array $row) => $row['item']->item_name)
            ->all();

        $this->assertContains($soldOut->item_name, $names);
        $this->assertContains($low->item_name, $names);
        $this->assertNotContains('Plenty left', $names);
        $this->assertNotContains('Unlimited', $names);
    }

    /** Widget 12: cash waiting only counts past the 10 minute mark. */
    public function test_needs_attention_counts_stock_conflicts_refunds_and_stale_cash(): void
    {
        $conflicted = $this->paidOrder(30.00);
        $conflicted->forceFill(['has_stock_conflict' => true])->save();

        $refundOrder = $this->paidOrder(20.00);
        Refund::create([
            'order_id' => $refundOrder->order_id,
            'requested_by_staff_id' => Staff::factory()->create()->staff_id,
            'method' => RefundMethod::Manual,
            'quantity' => 0,
            'amount' => 20.00,
            'reason' => 'Spilled in transit',
            'status' => RefundStatus::Requested,
        ]);

        $stale = $this->paidOrder(25.00);
        Payment::factory()->create([
            'order_id' => $stale->order_id,
            'method' => PaymentMethod::Cash,
            'status' => PaymentAttemptStatus::Pending,
            'created_at' => now()->subMinutes(15),
        ]);

        $fresh = $this->paidOrder(25.00);
        Payment::factory()->create([
            'order_id' => $fresh->order_id,
            'method' => PaymentMethod::Cash,
            'status' => PaymentAttemptStatus::Pending,
            'created_at' => now()->subMinutes(2),
        ]);

        $attention = app(ReportService::class)->needsAttention();

        $this->assertCount(1, $attention['stock_conflicts']);
        $this->assertCount(1, $attention['refund_requests']);
        $this->assertCount(1, $attention['cash_waiting']);
        $this->assertSame(3, $attention['total']);
    }

    /** Widgets 9 and 10 zero-fill, so the charts never have gaps. */
    public function test_the_charts_zero_fill_quiet_hours_and_closed_days(): void
    {
        $this->paidOrder(80.00, today()->setHour(19));

        $service = app(ReportService::class);

        $byHour = collect($service->salesByHourToday());
        $this->assertCount(24, $byHour);
        $this->assertSame(80.00, $byHour->firstWhere('hour', 19)['amount']);
        $this->assertSame(0.0, $byHour->firstWhere('hour', 3)['amount']);

        $trend = $service->salesTrend(7);
        $this->assertCount(7, $trend);
        $this->assertSame(today()->toDateString(), $trend[6]['date']);
        $this->assertSame(80.00, $trend[6]['amount']);
        $this->assertSame(0.0, $trend[0]['amount']);
    }

    private function lineFor(Order $order, MenuItem $item, int $quantity, float $lineTotal, int $lineNo): void
    {
        $size = $item->sizes()->create(['size_name' => 'Regular', 'price' => 20]);

        $order->items()->create([
            'line_no' => $lineNo,
            'item_id' => $item->item_id,
            'size_id' => $size->size_id,
            'item_name' => $item->item_name,
            'size_name' => 'Regular',
            'destination' => $item->destination,
            'quantity' => $quantity,
            'original_unit_price' => 20,
            'unit_price' => 20,
            'line_total' => $lineTotal,
        ]);
    }
}
