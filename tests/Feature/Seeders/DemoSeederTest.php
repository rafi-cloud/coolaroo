<?php

namespace Tests\Feature\Seeders;

use App\Models\Allergen;
use App\Models\Customer;
use App\Models\DietaryTag;
use App\Models\Feedback;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\Reservation;
use App\Models\RestaurantTable;
use App\Models\Staff;
use App\Models\Visit;
use App\Services\ReportService;
use App\Services\SpecialsService;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_seeder_populates_realistic_volume_and_powers_dashboard(): void
    {
        $this->seed(DemoSeeder::class);

        $this->assertGreaterThanOrEqual(4, Staff::count());
        $this->assertGreaterThanOrEqual(10, Customer::count());
        $this->assertGreaterThanOrEqual(12, RestaurantTable::count());

        $this->assertGreaterThanOrEqual(10, MenuCategory::count());
        $this->assertGreaterThanOrEqual(45, MenuItem::count());
        $this->assertGreaterThanOrEqual(10, Allergen::count());
        $this->assertGreaterThanOrEqual(6, DietaryTag::count());

        $this->assertSame(0, MenuCategory::doesntHave('menuItems')->count());

        $specials = app(SpecialsService::class);
        $topSpecial = $specials->topSpecial();
        $this->assertNotNull($topSpecial);
        $this->assertSame('Angus Ribeye 300g', $topSpecial['size']->menuItem->item_name);

        $this->assertGreaterThanOrEqual(10, Reservation::count());
        $this->assertGreaterThanOrEqual(8, Visit::count());

        $this->assertGreaterThanOrEqual(10, Order::count());
        $this->assertGreaterThanOrEqual(10, Payment::count());
        $this->assertGreaterThanOrEqual(1, Refund::count());

        $this->assertSame(0, Order::query()
            ->whereRaw('ROUND(total_amount, 2) != (SELECT ROUND(COALESCE(SUM(line_total), 0), 2) FROM order_item WHERE order_item.order_id = orders.order_id)')
            ->count());
        $this->assertSame(0, Order::doesntHave('statusHistory')->count());

        $this->assertSame(14, Reservation::query()
            ->whereDate('booking_date', '>=', today()->addDay())
            ->whereDate('booking_date', '<=', today()->addDays(14))
            ->distinct()
            ->count('booking_date'));

        $this->assertGreaterThanOrEqual(10, Feedback::where('is_hidden', false)->count());
        $this->assertSame(3, Feedback::where('is_featured', true)->count());
        $this->assertGreaterThanOrEqual(1, Feedback::where('is_hidden', true)->count());

        $reports = app(ReportService::class);
        $dashboard = $reports->dashboard();

        $this->assertGreaterThan(0, $dashboard['sales_today']['amount']);
        $this->assertGreaterThan(0, $dashboard['orders_today']);
        $this->assertGreaterThan(0, $dashboard['average_order_value']);
        $this->assertNotEmpty($dashboard['cash_vs_stripe']);
        $this->assertGreaterThan(0, $dashboard['covers_booked_today']);
        $this->assertGreaterThanOrEqual(1, $dashboard['pending_reservation_requests']);
        $this->assertGreaterThanOrEqual(1, $dashboard['open_refund_requests']);
        $this->assertNotNull($dashboard['average_rating']);
        $this->assertCount(24, $dashboard['sales_by_hour']);
        $this->assertNotEmpty($dashboard['top_items_today']);
        $this->assertNotEmpty($dashboard['low_stock']);
        $this->assertNotEmpty($dashboard['latest_feedback']);
        $this->assertNotEmpty($dashboard['needs_attention']);
    }
}
