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

        // Core records
        $this->assertGreaterThanOrEqual(4, Staff::count());
        $this->assertGreaterThanOrEqual(10, Customer::count());
        $this->assertGreaterThanOrEqual(12, RestaurantTable::count());

        // Catalogue
        $this->assertGreaterThanOrEqual(10, MenuCategory::count());
        $this->assertGreaterThanOrEqual(12, MenuItem::count());
        $this->assertGreaterThanOrEqual(10, Allergen::count());
        $this->assertGreaterThanOrEqual(6, DietaryTag::count());

        // Specials active
        $specials = app(SpecialsService::class);
        $topSpecial = $specials->topSpecial();
        $this->assertNotNull($topSpecial);
        $this->assertSame('Angus Ribeye 300g', $topSpecial['size']->menuItem->item_name);

        // Bookings and visits
        $this->assertGreaterThanOrEqual(10, Reservation::count());
        $this->assertGreaterThanOrEqual(8, Visit::count());

        // Orders, lines, payments
        $this->assertGreaterThanOrEqual(10, Order::count());
        $this->assertGreaterThanOrEqual(10, Payment::count());
        $this->assertGreaterThanOrEqual(1, Refund::count());

        // Feedback
        $this->assertGreaterThanOrEqual(10, Feedback::where('is_hidden', false)->count());
        $this->assertSame(3, Feedback::where('is_featured', true)->count());
        $this->assertGreaterThanOrEqual(1, Feedback::where('is_hidden', true)->count());

        // Dashboard widgets evaluation
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
