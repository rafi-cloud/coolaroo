<?php

namespace Tests\Feature\Staff;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\ReservationStatus;
use App\Enums\TableStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\RestaurantTable;
use App\Models\Role;
use App\Models\Setting;
use App\Models\Staff;
use App\Models\Visit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FloorViewTest extends TestCase
{
    use RefreshDatabase;

    private function staffWithRole(string $role): Staff
    {
        $roleModel = Role::firstWhere('role_name', $role) ?? Role::factory()->{$role}()->create();

        return Staff::factory()->create(['role_id' => $roleModel->role_id]);
    }

    public function test_a_reserved_table_offers_its_confirmed_booking_instead_of_a_dead_end(): void
    {
        $table = RestaurantTable::factory()->create(['status' => TableStatus::Reserved]);
        $reservation = Reservation::factory()->create([
            'booking_date' => now()->toDateString(),
            'status' => ReservationStatus::Confirmed,
        ]);
        Visit::create([
            'table_id' => $table->table_id,
            'reservation_id' => $reservation->reservation_id,
            'opened_at' => null,
        ]);

        $this->actingAs($this->staffWithRole('waitstaff'), 'staff')
            ->get(route('staff.floor.index'))
            ->assertOk()
            ->assertSee('floor-seat-reservation-select-'.$table->table_id, false)
            ->assertSee($reservation->reference_code)
            ->assertDontSee('floor-reserved-note-'.$table->table_id, false);
    }

    public function test_an_available_table_offers_todays_unassigned_bookings(): void
    {
        $table = RestaurantTable::factory()->create(['status' => TableStatus::Available]);
        $booking = Reservation::factory()->create([
            'booking_date' => now()->toDateString(),
            'status' => ReservationStatus::Confirmed,
        ]);

        $this->actingAs($this->staffWithRole('waitstaff'), 'staff')
            ->get(route('staff.floor.index'))
            ->assertOk()
            ->assertSee('floor-assign-reservation-select-'.$table->table_id, false)
            ->assertSee($booking->reference_code);
    }

    public function test_an_admin_keeps_admin_navigation_on_the_shared_floor_screen(): void
    {
        $this->actingAs($this->staffWithRole('admin'), 'staff')
            ->get('/staff/floor')
            ->assertOk()
            ->assertSee('aria-label="Admin"', false)
            ->assertSee('data-testid="nav-admin-settings"', false)
            ->assertDontSee('aria-label="Staff"', false);
    }

    public function test_waitstaff_keep_staff_navigation_on_the_floor_screen(): void
    {
        $this->actingAs($this->staffWithRole('waitstaff'), 'staff')
            ->get('/staff/floor')
            ->assertOk()
            ->assertSee('aria-label="Staff"', false)
            ->assertDontSee('data-testid="nav-admin-settings"', false);
    }

    public function test_waitstaff_are_not_offered_station_links_they_cannot_open(): void
    {
        $this->actingAs($this->staffWithRole('waitstaff'), 'staff')
            ->get(route('staff.floor.index'))
            ->assertOk()
            ->assertSee('data-testid="nav-staff-floor"', false)
            ->assertSee('data-testid="staff-logout"', false)
            ->assertDontSee('data-testid="nav-staff-kds-kitchen"', false)
            ->assertDontSee('data-testid="nav-staff-kds-bar"', false);
    }

    public function test_kitchen_staff_are_not_offered_floor_links_they_cannot_open(): void
    {
        $this->actingAs($this->staffWithRole('kitchen'), 'staff')
            ->get(route('staff.kds.kitchen'))
            ->assertOk()
            ->assertSee('data-testid="nav-staff-kds-kitchen"', false)
            ->assertDontSee('data-testid="nav-staff-floor"', false)
            ->assertDontSee('data-testid="nav-staff-reservations"', false);
    }

    public function test_a_cash_request_raises_an_alert_and_marks_its_table(): void
    {
        $table = RestaurantTable::factory()->create();
        $order = Order::factory()->create(['table_id' => $table->table_id]);
        $order->forceFill(['status' => OrderStatus::PendingPayment])->save();
        Payment::factory()->create(['order_id' => $order->order_id, 'method' => PaymentMethod::Cash]);

        $this->actingAs($this->staffWithRole('waitstaff'), 'staff')
            ->get(route('staff.floor.index'))
            ->assertOk()
            ->assertSee('data-testid="floor-alert-'.$table->table_id.'"', false)
            ->assertSee('Cash requested')
            ->assertSee('needs-cash', false);
    }

    public function test_a_ready_order_raises_an_alert_and_marks_its_table(): void
    {
        $table = RestaurantTable::factory()->create();
        $order = Order::factory()->create(['table_id' => $table->table_id]);
        $order->forceFill(['status' => OrderStatus::Ready])->save();

        $this->actingAs($this->staffWithRole('waitstaff'), 'staff')
            ->get(route('staff.floor.index'))
            ->assertOk()
            ->assertSee('data-testid="floor-alert-'.$table->table_id.'"', false)
            ->assertSee('Ready to serve')
            ->assertSee('needs-ready', false);
    }

    public function test_a_quiet_floor_says_so_rather_than_showing_an_empty_box(): void
    {
        $this->actingAs($this->staffWithRole('waitstaff'), 'staff')
            ->get(route('staff.floor.index'))
            ->assertOk()
            ->assertSee('Nothing needs attention.');
    }

    public function test_the_live_payload_carries_the_same_attention_state(): void
    {
        $table = RestaurantTable::factory()->create();
        $order = Order::factory()->create(['table_id' => $table->table_id]);
        $order->forceFill(['status' => OrderStatus::Ready])->save();

        $this->actingAs($this->staffWithRole('waitstaff'), 'staff')
            ->getJson(route('staff.floor.state'))
            ->assertOk()
            ->assertJsonPath('attention.0.kind', 'ready')
            ->assertJsonPath('attention.0.table_id', $table->table_id);
    }

    public function test_the_floor_page_lists_ready_orders_and_cash_waiting(): void
    {
        $ready = Order::factory()->create();
        $ready->forceFill(['status' => OrderStatus::Ready])->save();

        $cashOrder = Order::factory()->create();
        $cashOrder->forceFill(['status' => OrderStatus::PendingPayment])->save();
        Payment::factory()->create(['order_id' => $cashOrder->order_id, 'method' => PaymentMethod::Cash]);

        $this->actingAs($this->staffWithRole('waitstaff'), 'staff')
            ->get(route('staff.floor.index'))
            ->assertOk()
            ->assertSee($ready->order_number)
            ->assertSee($cashOrder->order_number);
    }

    public function test_the_state_endpoint_reports_the_paused_setting(): void
    {
        Setting::create(['setting_key' => 'qr_ordering_enabled', 'value_type' => 'bool', 'setting_value' => '0']);

        $this->actingAs($this->staffWithRole('waitstaff'), 'staff')
            ->getJson(route('staff.floor.state'))
            ->assertOk()
            ->assertJsonPath('qr_ordering_paused', true);
    }

    public function test_a_tables_active_order_count_only_counts_active_statuses(): void
    {
        $table = RestaurantTable::factory()->create();

        $paidOrder = Order::factory()->paid()->create(['table_id' => $table->table_id]);
        $servedOrder = Order::factory()->create(['table_id' => $table->table_id]);
        $servedOrder->forceFill(['status' => OrderStatus::Served])->save();

        $response = $this->actingAs($this->staffWithRole('waitstaff'), 'staff')
            ->getJson(route('staff.floor.state'))
            ->assertOk();

        $tablePayload = collect($response->json('tables'))->firstWhere('table_id', $table->table_id);
        $this->assertSame(1, $tablePayload['active_order_count']);
    }

    public function test_kitchen_staff_cannot_reach_the_floor_view(): void
    {
        $this->actingAs($this->staffWithRole('kitchen'), 'staff')
            ->get(route('staff.floor.index'))
            ->assertForbidden();
    }
}
