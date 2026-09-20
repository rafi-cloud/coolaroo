<?php

namespace Tests\Feature\Admin;

use App\Enums\PaymentAttemptStatus;
use App\Enums\PaymentMethod;
use App\Enums\RefundMethod;
use App\Enums\RefundStatus;
use App\Enums\ReservationStatus;
use App\Models\AuditLog;
use App\Models\Feedback;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\Reservation;
use App\Models\RestaurantTable;
use App\Models\Role;
use App\Models\SlotCapacity;
use App\Models\Staff;
use App\Models\Visit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportsTest extends TestCase
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
        $this->get(route('admin.reports.index'))
            ->assertRedirect(route('staff.login'));

        $this->get(route('admin.reports.show', ['type' => 'sales']))
            ->assertRedirect(route('staff.login'));
    }

    public function test_waitstaff_cannot_access_reports(): void
    {
        $this->actingAs($this->waitstaff(), 'staff')
            ->get(route('admin.reports.show', ['type' => 'sales']))
            ->assertForbidden();
    }

    public function test_reports_index_redirects_to_sales_report(): void
    {
        $this->actingAs($this->admin(), 'staff')
            ->get(route('admin.reports.index'))
            ->assertRedirect(route('admin.reports.show', ['type' => 'sales']));
    }

    public function test_unknown_report_type_returns_404(): void
    {
        $this->actingAs($this->admin(), 'staff')
            ->get(route('admin.reports.show', ['type' => 'unknown-report']))
            ->assertNotFound();
    }

    public function test_admin_can_view_sales_report_fr82(): void
    {
        $admin = $this->admin();
        $table = RestaurantTable::factory()->create(['table_number' => 'T1']);
        $order = Order::factory()->paid()->create([
            'table_id' => $table->table_id,
            'total_amount' => 110.00,
            'gst_amount' => 10.00,
            'paid_at' => now(),
        ]);
        Payment::factory()->create([
            'order_id' => $order->order_id,
            'method' => PaymentMethod::Cash,
            'status' => PaymentAttemptStatus::Succeeded,
            'amount' => 110.00,
            'recorded_by_staff_id' => $admin->staff_id,
            'paid_at' => now(),
        ]);

        $response = $this->actingAs($admin, 'staff')
            ->get(route('admin.reports.show', ['type' => 'sales']))
            ->assertOk();

        $response->assertSee('data-testid="admin-report-sales"', false)
            ->assertSee('$110.00')
            ->assertSee('$10.00') // GST 110 / 11 = 10
            ->assertSee($admin->name);
    }

    public function test_admin_can_view_items_report_fr83(): void
    {
        $admin = $this->admin();
        $category = MenuCategory::factory()->create(['category_name' => 'Signature Burgers']);
        $item = MenuItem::factory()->create([
            'category_id' => $category->category_id,
            'item_name' => 'Classic Cheeseburger',
            'is_active' => true,
            'is_available' => true,
        ]);
        $size = $item->sizes()->create(['size_name' => 'Regular', 'price' => 20.00]);

        $order = Order::factory()->paid()->create(['total_amount' => 40.00, 'paid_at' => now()]);
        $order->items()->create([
            'line_no' => 1,
            'item_id' => $item->item_id,
            'size_id' => $size->size_id,
            'item_name' => 'Classic Cheeseburger',
            'size_name' => 'Regular',
            'destination' => 'kitchen',
            'quantity' => 2,
            'original_unit_price' => 20.00,
            'unit_price' => 20.00,
            'line_total' => 40.00,
        ]);

        $response = $this->actingAs($admin, 'staff')
            ->get(route('admin.reports.show', ['type' => 'items']))
            ->assertOk();

        $response->assertSee('data-testid="admin-report-items"', false)
            ->assertSee('Classic Cheeseburger')
            ->assertSee('Signature Burgers');
    }

    public function test_admin_can_view_operations_report_fr84(): void
    {
        $admin = $this->admin();
        $table = RestaurantTable::factory()->create(['table_number' => 'T5']);
        $visit = Visit::create([
            'table_id' => $table->table_id,
            'opened_at' => now()->subMinutes(80),
            'closed_at' => now(),
            'guest_count' => 2,
        ]);

        $order = Order::factory()->paid()->create([
            'table_id' => $table->table_id,
            'visit_id' => $visit->visit_id,
            'placed_at' => now()->subMinutes(75),
            'paid_at' => now()->subMinutes(70),
            'kitchen_eta_at' => now()->subMinutes(50),
            'ready_at' => now()->subMinutes(55),
        ]);

        $response = $this->actingAs($admin, 'staff')
            ->get(route('admin.reports.show', ['type' => 'operations']))
            ->assertOk();

        $response->assertSee('data-testid="admin-report-operations"', false)
            ->assertSee('80m') // 80 min turnover
            ->assertSee('100%'); // on time rate
    }

    public function test_admin_can_view_reservations_report_fr85(): void
    {
        $admin = $this->admin();
        $slot = SlotCapacity::factory()->create(['slot_time' => '18:00']);
        $res1 = Reservation::factory()->create([
            'slot_id' => $slot->slot_id,
            'booking_date' => today()->toDateString(),
            'booking_time' => '18:00',
            'party_size' => 6,
        ]);
        $res1->forceFill(['status' => ReservationStatus::Confirmed])->save();

        $res2 = Reservation::factory()->create([
            'slot_id' => $slot->slot_id,
            'booking_date' => today()->toDateString(),
            'booking_time' => '18:00',
            'party_size' => 2,
            'is_late_cancellation' => true,
        ]);
        $res2->forceFill(['status' => ReservationStatus::Cancelled])->save();

        $response = $this->actingAs($admin, 'staff')
            ->get(route('admin.reports.show', ['type' => 'reservations']))
            ->assertOk();

        $response->assertSee('data-testid="admin-report-reservations"', false)
            ->assertSee('6 total dining covers')
            ->assertSee('Late cancellations: 1');
    }

    public function test_admin_can_view_feedback_report_fr86(): void
    {
        $admin = $this->admin();
        $order = Order::factory()->paid()->create(['paid_at' => now()]);
        Feedback::factory()->create([
            'order_id' => $order->order_id,
            'food_rating' => 5,
            'service_rating' => 5,
            'comment' => 'Exquisite culinary delight',
            'submitted_at' => now(),
            'is_hidden' => false,
        ]);
        Feedback::factory()->create([
            'order_id' => Order::factory()->paid()->create(['paid_at' => now()])->order_id,
            'food_rating' => 1,
            'service_rating' => 1,
            'is_hidden' => true,
            'hidden_reason' => 'Profanity in comment',
            'submitted_at' => now(),
        ]);

        $response = $this->actingAs($admin, 'staff')
            ->get(route('admin.reports.show', ['type' => 'feedback']))
            ->assertOk();

        $response->assertSee('data-testid="admin-report-feedback"', false)
            ->assertSee('Exquisite culinary delight')
            ->assertSee('Overall venue rating');
    }

    public function test_admin_can_view_staff_activity_report_fr87(): void
    {
        $admin = $this->admin();
        $waitstaff = $this->waitstaff();

        $order = Order::factory()->paid()->create(['paid_at' => now()]);
        Payment::factory()->create([
            'order_id' => $order->order_id,
            'method' => PaymentMethod::Cash,
            'status' => PaymentAttemptStatus::Succeeded,
            'amount' => 75.00,
            'recorded_by_staff_id' => $waitstaff->staff_id,
            'paid_at' => now(),
        ]);

        Refund::create([
            'order_id' => $order->order_id,
            'requested_by_staff_id' => $waitstaff->staff_id,
            'method' => RefundMethod::Cash,
            'quantity' => 1,
            'amount' => 20.00,
            'reason' => 'Customer changed mind',
            'status' => RefundStatus::Requested,
            'requested_at' => now(),
        ]);

        AuditLog::create([
            'staff_id' => $waitstaff->staff_id,
            'action_type' => 'table_override',
            'entity_name' => 'restaurant_table',
            'entity_id' => 1,
            'logged_at' => now(),
        ]);

        $response = $this->actingAs($admin, 'staff')
            ->get(route('admin.reports.show', ['type' => 'staff']))
            ->assertOk();

        $response->assertSee('data-testid="admin-report-staff-activity"', false)
            ->assertSee($waitstaff->name)
            ->assertSee('$75.00');
    }
}
