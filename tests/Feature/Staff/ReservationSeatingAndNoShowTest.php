<?php

namespace Tests\Feature\Staff;

use App\Enums\ReservationStatus;
use App\Enums\TableStatus;
use App\Enums\VisitCloseReason;
use App\Models\Customer;
use App\Models\Reservation;
use App\Models\RestaurantTable;
use App\Models\Role;
use App\Models\SlotCapacity;
use App\Models\Staff;
use App\Models\Visit;
use App\Services\TrustService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class ReservationSeatingAndNoShowTest extends TestCase
{
    use RefreshDatabase;

    private Staff $waitstaff;

    private Staff $admin;

    private Staff $kitchen;

    private SlotCapacity $slot;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-09-22 12:00:00', 'Australia/Melbourne'));

        $waitstaffRole = Role::firstOrCreate(['role_name' => 'waitstaff'], [
            'description' => 'Waitstaff',
            'landing_screen' => 'staff.floor.index',
        ]);
        $adminRole = Role::firstOrCreate(['role_name' => 'admin'], [
            'description' => 'Admin',
            'landing_screen' => 'admin.dashboard',
        ]);
        $kitchenRole = Role::firstOrCreate(['role_name' => 'kitchen'], [
            'description' => 'Kitchen',
            'landing_screen' => 'staff.kds.index',
        ]);

        $this->waitstaff = Staff::factory()->create(['role_id' => $waitstaffRole->role_id]);
        $this->admin = Staff::factory()->create(['role_id' => $adminRole->role_id]);
        $this->kitchen = Staff::factory()->create(['role_id' => $kitchenRole->role_id]);

        $this->slot = SlotCapacity::factory()->create([
            'slot_time' => '18:00',
            'max_covers' => 20,
            'is_active' => true,
        ]);

        $this->customer = Customer::factory()->create([
            'full_name' => 'James Howlett',
            'email' => 'logan@example.com',
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_waitstaff_can_seat_confirmed_reservation_with_assigned_tables(): void
    {
        $table = RestaurantTable::factory()->create([
            'table_number' => 30,
            'seat_capacity' => 4,
            'is_active' => true,
            'status' => TableStatus::Reserved,
        ]);

        $reservation = Reservation::factory()->create([
            'customer_id' => $this->customer->customer_id,
            'booking_date' => '2026-09-22',
            'slot_id' => $this->slot->slot_id,
            'booking_time' => '18:00',
            'party_size' => 4,
            'status' => ReservationStatus::Confirmed,
        ]);

        $visit = Visit::create([
            'table_id' => $table->table_id,
            'reservation_id' => $reservation->reservation_id,
            'opened_at' => null,
            'guest_count' => 4,
        ]);

        $response = $this->actingAs($this->waitstaff, 'staff')
            ->withSession(['staff_last_activity' => now()])
            ->post(route('staff.reservations.seat', $reservation));

        $response->assertRedirect();
        $response->assertSessionHas('status', 'reservation-seated');

        $reservation->refresh();
        $this->assertSame(ReservationStatus::Seated, $reservation->status);
        $this->assertNotNull($reservation->seated_at);

        $visit->refresh();
        $this->assertNotNull($visit->opened_at);
        $this->assertSame($this->waitstaff->staff_id, $visit->opened_by_staff_id);

        $table->refresh();
        $this->assertSame(TableStatus::Occupied, $table->status);
    }

    public function test_waitstaff_can_seat_a_confirmed_reservation_from_the_floor_drawer(): void
    {
        $table = RestaurantTable::factory()->create([
            'table_number' => 31,
            'seat_capacity' => 4,
            'is_active' => true,
            'status' => TableStatus::Reserved,
        ]);

        $reservation = Reservation::factory()->create([
            'customer_id' => $this->customer->customer_id,
            'booking_date' => '2026-09-22',
            'slot_id' => $this->slot->slot_id,
            'booking_time' => '18:00',
            'party_size' => 4,
            'status' => ReservationStatus::Confirmed,
        ]);

        $visit = Visit::create([
            'table_id' => $table->table_id,
            'reservation_id' => $reservation->reservation_id,
            'opened_at' => null,
            'guest_count' => 4,
        ]);

        $this->actingAs($this->waitstaff, 'staff')
            ->withSession(['staff_last_activity' => now()])
            ->post(route('staff.tables.seat-reservation', $table), ['reservation_id' => $reservation->reservation_id])
            ->assertRedirect()
            ->assertSessionHas('status', 'reservation-seated');

        $this->assertSame(ReservationStatus::Seated, $reservation->fresh()->status);
        $this->assertNotNull($visit->fresh()->opened_at);
        $this->assertSame(TableStatus::Occupied, $table->fresh()->status);
    }

    public function test_the_floor_drawer_refuses_a_booking_assigned_to_another_table(): void
    {
        $assigned = RestaurantTable::factory()->create(['table_number' => 32, 'status' => TableStatus::Reserved]);
        $other = RestaurantTable::factory()->create(['table_number' => 33, 'status' => TableStatus::Available]);

        $reservation = Reservation::factory()->create([
            'customer_id' => $this->customer->customer_id,
            'booking_date' => '2026-09-22',
            'slot_id' => $this->slot->slot_id,
            'booking_time' => '18:00',
            'party_size' => 2,
            'status' => ReservationStatus::Confirmed,
        ]);

        Visit::create([
            'table_id' => $assigned->table_id,
            'reservation_id' => $reservation->reservation_id,
            'opened_at' => null,
        ]);

        $this->actingAs($this->waitstaff, 'staff')
            ->withSession(['staff_last_activity' => now()])
            ->post(route('staff.tables.seat-reservation', $other), ['reservation_id' => $reservation->reservation_id])
            ->assertSessionHasErrors('reservation_id');

        $this->assertSame(ReservationStatus::Confirmed, $reservation->fresh()->status);
    }

    public function test_cannot_seat_reservation_without_assigned_tables(): void
    {
        $reservation = Reservation::factory()->create([
            'customer_id' => $this->customer->customer_id,
            'booking_date' => '2026-09-22',
            'slot_id' => $this->slot->slot_id,
            'booking_time' => '18:00',
            'party_size' => 4,
            'status' => ReservationStatus::Confirmed,
        ]);

        $response = $this->actingAs($this->waitstaff, 'staff')
            ->withSession(['staff_last_activity' => now()])
            ->post(route('staff.reservations.seat', $reservation));

        $response->assertSessionHasErrors('table');

        $reservation->refresh();
        $this->assertSame(ReservationStatus::Confirmed, $reservation->status);
    }

    public function test_waitstaff_can_mark_confirmed_reservation_as_no_show_after_grace(): void
    {
        $table = RestaurantTable::factory()->create([
            'table_number' => 31,
            'seat_capacity' => 4,
            'is_active' => true,
            'status' => TableStatus::Reserved,
        ]);

        $reservation = Reservation::factory()->create([
            'customer_id' => $this->customer->customer_id,
            'booking_date' => '2026-09-22',
            'slot_id' => $this->slot->slot_id,
            'booking_time' => '18:00',
            'party_size' => 4,
            'status' => ReservationStatus::Confirmed,
        ]);

        $visit = Visit::create([
            'table_id' => $table->table_id,
            'reservation_id' => $reservation->reservation_id,
            'opened_at' => null,
            'guest_count' => 4,
        ]);

        Carbon::setTestNow(Carbon::parse('2026-09-22 18:20:00', 'Australia/Melbourne'));

        $response = $this->actingAs($this->waitstaff, 'staff')
            ->withSession(['staff_last_activity' => now()])
            ->post(route('staff.reservations.no-show', $reservation));

        $response->assertRedirect();
        $response->assertSessionHas('status', 'reservation-no-show');

        $reservation->refresh();
        $this->assertSame(ReservationStatus::NoShow, $reservation->status);
        $this->assertNotNull($reservation->no_show_at);
        $this->assertSame($this->waitstaff->staff_id, $reservation->no_show_by_staff_id);

        $visit->refresh();
        $this->assertNotNull($visit->closed_at);
        $this->assertSame(VisitCloseReason::NoShow, $visit->close_reason);

        $table->refresh();
        $this->assertSame(TableStatus::Available, $table->status);

        $trustService = app(TrustService::class);
        $this->assertSame(TrustService::BADGE_FLAGGED, $trustService->badge($this->customer));
    }

    public function test_cannot_mark_no_show_before_grace_period_expires(): void
    {
        $reservation = Reservation::factory()->create([
            'customer_id' => $this->customer->customer_id,
            'booking_date' => '2026-09-22',
            'slot_id' => $this->slot->slot_id,
            'booking_time' => '18:00',
            'party_size' => 4,
            'status' => ReservationStatus::Confirmed,
        ]);

        Carbon::setTestNow(Carbon::parse('2026-09-22 18:05:00', 'Australia/Melbourne'));

        $response = $this->actingAs($this->waitstaff, 'staff')
            ->withSession(['staff_last_activity' => now()])
            ->post(route('staff.reservations.no-show', $reservation));

        $response->assertSessionHasErrors('status');

        $reservation->refresh();
        $this->assertSame(ReservationStatus::Confirmed, $reservation->status);
    }

    public function test_holder_scan_in_window_seats_all_linked_tables_and_visits(): void
    {
        $table1 = RestaurantTable::factory()->create([
            'table_number' => 32,
            'seat_capacity' => 4,
            'is_active' => true,
            'status' => TableStatus::Reserved,
        ]);

        $table2 = RestaurantTable::factory()->create([
            'table_number' => 33,
            'seat_capacity' => 4,
            'is_active' => true,
            'status' => TableStatus::Reserved,
        ]);

        $reservation = Reservation::factory()->create([
            'customer_id' => $this->customer->customer_id,
            'booking_date' => '2026-09-22',
            'slot_id' => $this->slot->slot_id,
            'booking_time' => '18:00',
            'party_size' => 8,
            'status' => ReservationStatus::Confirmed,
        ]);

        $visit1 = Visit::create([
            'table_id' => $table1->table_id,
            'reservation_id' => $reservation->reservation_id,
            'opened_at' => null,
            'guest_count' => 8,
        ]);

        $visit2 = Visit::create([
            'table_id' => $table2->table_id,
            'reservation_id' => $reservation->reservation_id,
            'opened_at' => null,
            'guest_count' => 8,
        ]);

        Carbon::setTestNow(Carbon::parse('2026-09-22 17:50:00', 'Australia/Melbourne'));

        $scanUrl = URL::signedRoute('table.scan', [
            'table' => $table1->table_id,
            'token' => $table1->qr_token,
        ]);

        $response = $this->actingAs($this->customer, 'customer')
            ->get($scanUrl);

        $response->assertRedirect(route('menu.index'));

        $table1->refresh();
        $table2->refresh();
        $this->assertSame(TableStatus::Occupied, $table1->status);
        $this->assertSame(TableStatus::Occupied, $table2->status);

        $visit1->refresh();
        $visit2->refresh();
        $this->assertNotNull($visit1->opened_at);
        $this->assertNotNull($visit2->opened_at);

        $reservation->refresh();
        $this->assertSame(ReservationStatus::Seated, $reservation->status);
    }

    public function test_kitchen_role_cannot_seat_or_mark_no_show(): void
    {
        $reservation = Reservation::factory()->create([
            'customer_id' => $this->customer->customer_id,
            'booking_date' => '2026-09-22',
            'slot_id' => $this->slot->slot_id,
            'booking_time' => '18:00',
            'party_size' => 4,
            'status' => ReservationStatus::Confirmed,
        ]);

        $seatRes = $this->actingAs($this->kitchen, 'staff')
            ->withSession(['staff_last_activity' => now()])
            ->post(route('staff.reservations.seat', $reservation));

        $seatRes->assertForbidden();

        $noShowRes = $this->actingAs($this->kitchen, 'staff')
            ->withSession(['staff_last_activity' => now()])
            ->post(route('staff.reservations.no-show', $reservation));

        $noShowRes->assertForbidden();
    }
}
