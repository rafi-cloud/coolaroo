<?php

namespace Tests\Feature\Staff;

use App\Enums\ReservationStatus;
use App\Enums\TableStatus;
use App\Enums\VisitCloseReason;
use App\Events\ReservationAlert as ReservationAlertEvent;
use App\Models\Customer;
use App\Models\Reservation;
use App\Models\RestaurantTable;
use App\Models\Role;
use App\Models\SlotCapacity;
use App\Models\Staff;
use App\Models\Visit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class TableAssignmentTest extends TestCase
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
            'full_name' => 'Alice Walker',
            'email' => 'alice@example.com',
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_waitstaff_can_assign_tables_to_confirmed_reservation(): void
    {
        $table1 = RestaurantTable::factory()->create([
            'table_number' => 10,
            'seat_capacity' => 4,
            'is_active' => true,
            'status' => TableStatus::Available,
        ]);

        $reservation = Reservation::factory()->create([
            'customer_id' => $this->customer->customer_id,
            'booking_date' => '2026-09-25',
            'slot_id' => $this->slot->slot_id,
            'party_size' => 4,
            'status' => ReservationStatus::Confirmed,
        ]);

        $response = $this->actingAs($this->waitstaff, 'staff')
            ->withSession(['staff_last_activity' => now()])
            ->post(route('staff.tables.assign-reservation', $table1), [
                'reservation_id' => $reservation->reservation_id,
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('status', 'reservation-assigned');

        // Check visit row created with opened_at = null (BR04, FR65)
        $visit = Visit::where('reservation_id', $reservation->reservation_id)
            ->where('table_id', $table1->table_id)
            ->first();

        $this->assertNotNull($visit);
        $this->assertNull($visit->opened_at);
        $this->assertNull($visit->closed_at);

        // Booking is more than 30 mins in future; table stays Available
        $table1->refresh();
        $this->assertSame(TableStatus::Available, $table1->status);
    }

    public function test_cannot_assign_inactive_tables(): void
    {
        $table = RestaurantTable::factory()->create([
            'table_number' => 11,
            'seat_capacity' => 4,
            'is_active' => false,
            'status' => TableStatus::Available,
        ]);

        $reservation = Reservation::factory()->create([
            'customer_id' => $this->customer->customer_id,
            'booking_date' => '2026-09-25',
            'slot_id' => $this->slot->slot_id,
            'party_size' => 2,
            'status' => ReservationStatus::Confirmed,
        ]);

        $response = $this->actingAs($this->waitstaff, 'staff')
            ->withSession(['staff_last_activity' => now()])
            ->post(route('staff.tables.assign-reservation', $table), [
                'reservation_id' => $reservation->reservation_id,
            ]);

        $response->assertSessionHasErrors('table_ids');
    }

    public function test_cannot_assign_tables_with_insufficient_seats(): void
    {
        $table = RestaurantTable::factory()->create([
            'table_number' => 12,
            'seat_capacity' => 2,
            'is_active' => true,
            'status' => TableStatus::Available,
        ]);

        $reservation = Reservation::factory()->create([
            'customer_id' => $this->customer->customer_id,
            'booking_date' => '2026-09-25',
            'slot_id' => $this->slot->slot_id,
            'party_size' => 6,
            'status' => ReservationStatus::Confirmed,
        ]);

        $response = $this->actingAs($this->waitstaff, 'staff')
            ->withSession(['staff_last_activity' => now()])
            ->post(route('staff.tables.assign-reservation', $table), [
                'reservation_id' => $reservation->reservation_id,
            ]);

        $response->assertSessionHasErrors('table_ids');
    }

    public function test_cannot_assign_table_with_overlapping_reservation_time(): void
    {
        $table = RestaurantTable::factory()->create([
            'table_number' => 14,
            'seat_capacity' => 4,
            'is_active' => true,
            'status' => TableStatus::Available,
        ]);

        $slot2 = SlotCapacity::factory()->create([
            'slot_time' => '18:30',
            'max_covers' => 20,
            'is_active' => true,
        ]);

        // Res 1: 18:00 (duration 120m for 4 covers -> 18:00 to 20:00)
        $res1 = Reservation::factory()->create([
            'customer_id' => $this->customer->customer_id,
            'booking_date' => '2026-09-25',
            'slot_id' => $this->slot->slot_id,
            'party_size' => 4,
            'status' => ReservationStatus::Confirmed,
        ]);

        // Res 2: 18:30 (overlaps 18:00 - 20:00)
        $res2 = Reservation::factory()->create([
            'customer_id' => $this->customer->customer_id,
            'booking_date' => '2026-09-25',
            'slot_id' => $slot2->slot_id,
            'party_size' => 4,
            'status' => ReservationStatus::Confirmed,
        ]);

        // Assign table to res1
        $this->actingAs($this->waitstaff, 'staff')
            ->withSession(['staff_last_activity' => now()])
            ->post(route('staff.tables.assign-reservation', $table), [
                'reservation_id' => $res1->reservation_id,
            ]);

        // Try assigning table to res2 -> fails overlap check (BR33)
        $response = $this->actingAs($this->waitstaff, 'staff')
            ->withSession(['staff_last_activity' => now()])
            ->post(route('staff.tables.assign-reservation', $table), [
                'reservation_id' => $res2->reservation_id,
            ]);

        $response->assertSessionHasErrors('table_ids');
    }

    public function test_assigning_inside_t30_transitions_table_to_reserved_and_fires_alert(): void
    {
        Event::fake([ReservationAlertEvent::class]);

        // Set time to 17:40 on booking date (20 mins before 18:00)
        Carbon::setTestNow(Carbon::parse('2026-09-25 17:40:00', 'Australia/Melbourne'));

        $table = RestaurantTable::factory()->create([
            'table_number' => 15,
            'seat_capacity' => 4,
            'is_active' => true,
            'status' => TableStatus::Available,
        ]);

        $reservation = Reservation::factory()->create([
            'customer_id' => $this->customer->customer_id,
            'booking_date' => '2026-09-25',
            'slot_id' => $this->slot->slot_id,
            'party_size' => 4,
            'status' => ReservationStatus::Confirmed,
        ]);

        $this->actingAs($this->waitstaff, 'staff')
            ->withSession(['staff_last_activity' => now()])
            ->post(route('staff.tables.assign-reservation', $table), [
                'reservation_id' => $reservation->reservation_id,
            ]);

        $table->refresh();
        $this->assertSame(TableStatus::Reserved, $table->status);

        Event::assertDispatched(ReservationAlertEvent::class, function ($event) use ($reservation) {
            return $event->kind === ReservationAlertEvent::PLACE_SIGN
                && $event->reservation->reservation_id === $reservation->reservation_id;
        });
    }

    public function test_unassign_tables_closes_visits_and_reverts_reserved_table_to_available(): void
    {
        $table = RestaurantTable::factory()->create([
            'table_number' => 16,
            'seat_capacity' => 4,
            'is_active' => true,
            'status' => TableStatus::Reserved,
        ]);

        $reservation = Reservation::factory()->create([
            'customer_id' => $this->customer->customer_id,
            'booking_date' => '2026-09-25',
            'slot_id' => $this->slot->slot_id,
            'party_size' => 4,
            'status' => ReservationStatus::Confirmed,
        ]);

        Visit::create([
            'table_id' => $table->table_id,
            'reservation_id' => $reservation->reservation_id,
            'opened_at' => null,
            'opened_by_staff_id' => $this->waitstaff->staff_id,
        ]);

        $response = $this->actingAs($this->waitstaff, 'staff')
            ->withSession(['staff_last_activity' => now()])
            ->delete(route('staff.tables.release-reservation', $table), [
                'reservation_id' => $reservation->reservation_id,
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('status', 'reservation-released');

        // Table reverted to Available (BR64)
        $table->refresh();
        $this->assertSame(TableStatus::Available, $table->status);

        // Visit marked closed with reason unassigned (BR64)
        $visit = Visit::where('reservation_id', $reservation->reservation_id)->first();
        $this->assertNotNull($visit->closed_at);
        $this->assertSame(VisitCloseReason::Unassigned, $visit->close_reason);
    }

    /**
     * FR65 from S23: each drawer adds its own table, so a party too big for one
     * table is assigned by repeating the action — the seats check then passes
     * across the pair, where either table alone would have been refused.
     */
    public function test_assigning_a_second_table_adds_it_to_the_same_booking(): void
    {
        $table1 = RestaurantTable::factory()->create([
            'table_number' => 21,
            'seat_capacity' => 4,
            'is_active' => true,
            'status' => TableStatus::Available,
        ]);

        $table2 = RestaurantTable::factory()->create([
            'table_number' => 22,
            'seat_capacity' => 4,
            'is_active' => true,
            'status' => TableStatus::Available,
        ]);

        $reservation = Reservation::factory()->create([
            'customer_id' => $this->customer->customer_id,
            'booking_date' => '2026-09-25',
            'slot_id' => $this->slot->slot_id,
            'party_size' => 6,
            'status' => ReservationStatus::Confirmed,
        ]);

        $this->actingAs($this->waitstaff, 'staff')
            ->withSession(['staff_last_activity' => now()])
            ->post(route('staff.tables.assign-reservation', $table1), [
                'reservation_id' => $reservation->reservation_id,
            ])
            ->assertSessionHasErrors('table_ids');

        $table1->forceFill(['seat_capacity' => 6])->save();

        $this->actingAs($this->waitstaff, 'staff')
            ->withSession(['staff_last_activity' => now()])
            ->post(route('staff.tables.assign-reservation', $table1), [
                'reservation_id' => $reservation->reservation_id,
            ]);

        $this->actingAs($this->waitstaff, 'staff')
            ->withSession(['staff_last_activity' => now()])
            ->post(route('staff.tables.assign-reservation', $table2), [
                'reservation_id' => $reservation->reservation_id,
            ]);

        foreach ([$table1, $table2] as $table) {
            $this->assertDatabaseHas('visit', [
                'reservation_id' => $reservation->reservation_id,
                'table_id' => $table->table_id,
                'closed_at' => null,
            ]);
        }

        $this->actingAs($this->waitstaff, 'staff')
            ->withSession(['staff_last_activity' => now()])
            ->delete(route('staff.tables.release-reservation', $table2), [
                'reservation_id' => $reservation->reservation_id,
            ]);

        $this->assertSame(
            [$table1->table_id],
            Visit::where('reservation_id', $reservation->reservation_id)
                ->whereNull('closed_at')
                ->pluck('table_id')
                ->all()
        );
    }

    public function test_kitchen_role_cannot_assign_tables(): void
    {
        $table = RestaurantTable::factory()->create([
            'table_number' => 25,
            'seat_capacity' => 2,
            'is_active' => true,
        ]);

        $reservation = Reservation::factory()->create([
            'customer_id' => $this->customer->customer_id,
            'booking_date' => '2026-09-25',
            'slot_id' => $this->slot->slot_id,
            'party_size' => 2,
            'status' => ReservationStatus::Confirmed,
        ]);

        $response = $this->actingAs($this->kitchen, 'staff')
            ->withSession(['staff_last_activity' => now()])
            ->post(route('staff.tables.assign-reservation', $table), [
                'reservation_id' => $reservation->reservation_id,
            ]);

        $response->assertForbidden();
    }
}
