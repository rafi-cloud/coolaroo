<?php

namespace Tests\Feature\Admin;

use App\Enums\OrderStatus;
use App\Enums\PaymentAttemptStatus;
use App\Enums\PaymentMethod;
use App\Enums\ReservationStatus;
use App\Models\Feedback;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\Reservation;
use App\Models\RestaurantTable;
use App\Models\Role;
use App\Models\SlotCapacity;
use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): Staff
    {
        return Staff::factory()->create(['role_id' => Role::factory()->admin()->create()->role_id]);
    }

    private function waitstaff(): Staff
    {
        return Staff::factory()->create(['role_id' => Role::factory()->waitstaff()->create()->role_id]);
    }

    public function test_guest_is_redirected_to_staff_login(): void
    {
        $this->get(route('admin.dashboard'))
            ->assertRedirect(route('staff.login'));
    }

    public function test_waitstaff_cannot_access_admin_dashboard(): void
    {
        $this->actingAs($this->waitstaff(), 'staff')
            ->get(route('admin.dashboard'))
            ->assertForbidden();
    }

    public function test_admin_can_access_dashboard_and_see_all_15_widgets(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin, 'staff')
            ->get(route('admin.dashboard'))
            ->assertOk();

        // Check 8 KPI Tiles
        $response->assertSee('data-testid="admin-tile-sales"', false)
            ->assertSee('data-testid="admin-tile-orders"', false)
            ->assertSee('data-testid="admin-tile-aov"', false)
            ->assertSee('data-testid="admin-tile-payment-split"', false)
            ->assertSee('data-testid="admin-tile-covers"', false)
            ->assertSee('data-testid="admin-tile-reservations"', false)
            ->assertSee('data-testid="admin-tile-refunds"', false)
            ->assertSee('data-testid="admin-tile-rating"', false);

        // Check 3 Charts
        $response->assertSee('data-testid="admin-chart-hourly"', false)
            ->assertSee('data-testid="admin-chart-trend"', false)
            ->assertSee('data-testid="admin-chart-status"', false);

        // Check 4 Lists
        $response->assertSee('data-testid="admin-list-attention"', false)
            ->assertSee('data-testid="admin-list-top-items"', false)
            ->assertSee('data-testid="admin-list-low-stock"', false)
            ->assertSee('data-testid="admin-list-feedback"', false);
    }

    public function test_dashboard_renders_live_data_across_widgets(): void
    {
        $admin = $this->admin();

        // 1. Paid order with lines
        $table = RestaurantTable::factory()->create(['table_number' => 'T10']);
        $item = MenuItem::factory()->create(['item_name' => 'Signature Burger']);
        $size = $item->sizes()->create(['size_name' => 'Regular', 'price' => 27.00]);
        $order = Order::factory()->paid()->create([
            'table_id' => $table->table_id,
            'total_amount' => 54.00,
            'paid_at' => now(),
        ]);
        $order->items()->create([
            'line_no' => 1,
            'item_id' => $item->item_id,
            'size_id' => $size->size_id,
            'item_name' => 'Signature Burger',
            'size_name' => 'Regular',
            'destination' => $item->destination,
            'quantity' => 2,
            'original_unit_price' => 27.00,
            'unit_price' => 27.00,
            'line_total' => 54.00,
        ]);
        Payment::factory()->create([
            'order_id' => $order->order_id,
            'method' => PaymentMethod::Cash,
            'status' => PaymentAttemptStatus::Succeeded,
            'amount' => 54.00,
            'paid_at' => now(),
        ]);

        // 2. Reservation today
        $slot = SlotCapacity::factory()->create(['slot_time' => '19:00']);
        Reservation::factory()->create([
            'slot_id' => $slot->slot_id,
            'booking_date' => today(),
            'booking_time' => '19:00',
            'party_size' => 4,
            'status' => ReservationStatus::Confirmed,
        ]);

        // 3. Feedback
        Feedback::factory()->create([
            'order_id' => $order->order_id,
            'food_rating' => 5,
            'service_rating' => 4,
            'comment' => 'Fantastic service!',
            'submitted_at' => now(),
            'is_hidden' => false,
        ]);

        // 4. Low stock item
        MenuItem::factory()->create([
            'item_name' => 'Limited Truffle Pasta',
            'is_active' => true,
            'is_available' => false,
        ]);

        $response = $this->actingAs($admin, 'staff')
            ->get(route('admin.dashboard'))
            ->assertOk();

        // Verify dynamic values rendered
        $response->assertSee('$54.00')
            ->assertSee('Signature Burger')
            ->assertSee('Fantastic service!')
            ->assertSee('Limited Truffle Pasta')
            ->assertSee('Sold out');
    }

    public function test_admin_can_toggle_sales_trend_window(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin, 'staff')
            ->get(route('admin.dashboard', ['trend' => 30]))
            ->assertOk()
            ->assertSee('data-trend-target="30"', false);
    }
}
