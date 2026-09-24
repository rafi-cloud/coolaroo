<?php

namespace Tests\Feature\Staff;

use App\Enums\ReservationStatus;
use App\Models\Customer;
use App\Models\Reservation;
use App\Models\Role;
use App\Models\SlotCapacity;
use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ReservationBoardTest extends TestCase
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
            'full_name' => 'Jane Smith',
            'email_verified_at' => now(),
            'phone' => '0411223344',
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_waitstaff_can_view_reservations_board(): void
    {
        $this->actingAs($this->waitstaff, 'staff')
            ->withSession(['staff_last_activity' => now()])
            ->get(route('staff.reservations.index'))
            ->assertOk()
            ->assertSee('Reservations board')
            ->assertSee('Manage guest bookings');
    }

    public function test_admin_can_view_reservations_board_via_role_bypass(): void
    {
        $this->actingAs($this->admin, 'staff')
            ->withSession(['staff_last_activity' => now()])
            ->get(route('staff.reservations.index'))
            ->assertOk()
            ->assertSee('Reservations board');
    }

    public function test_kitchen_staff_cannot_view_reservations_board(): void
    {
        $this->actingAs($this->kitchen, 'staff')
            ->withSession(['staff_last_activity' => now()])
            ->get(route('staff.reservations.index'))
            ->assertForbidden();
    }

    public function test_board_filters_by_date(): void
    {
        $todayRes = Reservation::factory()->create([
            'customer_id' => $this->customer->customer_id,
            'slot_id' => $this->slot->slot_id,
            'booking_date' => '2026-09-22',
            'booking_time' => '18:00',
            'reference_code' => 'CR-TODAY1',
        ]);

        $fridayRes = Reservation::factory()->create([
            'customer_id' => $this->customer->customer_id,
            'slot_id' => $this->slot->slot_id,
            'booking_date' => '2026-09-25',
            'booking_time' => '18:00',
            'reference_code' => 'CR-FRIDAY',
        ]);

        $this->actingAs($this->waitstaff, 'staff')
            ->withSession(['staff_last_activity' => now()])
            ->get(route('staff.reservations.index', ['date' => '2026-09-22']))
            ->assertOk()
            ->assertSee('CR-TODAY1')
            ->assertDontSee('CR-FRIDAY');

        $this->actingAs($this->waitstaff, 'staff')
            ->withSession(['staff_last_activity' => now()])
            ->get(route('staff.reservations.index', ['date' => '2026-09-25']))
            ->assertOk()
            ->assertSee('CR-FRIDAY')
            ->assertDontSee('CR-TODAY1');
    }

    public function test_board_filters_by_status(): void
    {
        $requested = Reservation::factory()->create([
            'customer_id' => $this->customer->customer_id,
            'slot_id' => $this->slot->slot_id,
            'booking_date' => '2026-09-22',
            'booking_time' => '18:00',
            'reference_code' => 'CR-REQ001',
        ]);

        $confirmed = Reservation::factory()->confirmed()->create([
            'customer_id' => $this->customer->customer_id,
            'slot_id' => $this->slot->slot_id,
            'booking_date' => '2026-09-22',
            'booking_time' => '18:00',
            'reference_code' => 'CR-CONF01',
        ]);

        $this->actingAs($this->waitstaff, 'staff')
            ->withSession(['staff_last_activity' => now()])
            ->get(route('staff.reservations.index', ['date' => '2026-09-22', 'status' => 'requested']))
            ->assertOk()
            ->assertSee('CR-REQ001')
            ->assertDontSee('CR-CONF01');
    }

    public function test_unassigned_booking_inside_t30_is_highlighted(): void
    {
        $slot1220 = SlotCapacity::factory()->create(['slot_time' => '12:20', 'max_covers' => 20]);

        $urgentRes = Reservation::factory()->confirmed()->create([
            'customer_id' => $this->customer->customer_id,
            'slot_id' => $slot1220->slot_id,
            'booking_date' => '2026-09-22',
            'booking_time' => '12:20',
            'party_size' => 2,
        ]);

        $response = $this->actingAs($this->waitstaff, 'staff')
            ->withSession(['staff_last_activity' => now()])
            ->get(route('staff.reservations.index', ['date' => '2026-09-22']))
            ->assertOk();

        $response->assertSee('card-unassigned-t30');
        $response->assertSee('Unassigned Booking (T–30 min)');
    }

    public function test_staff_can_approve_requested_reservation_via_post(): void
    {
        $reservation = Reservation::factory()->create([
            'customer_id' => $this->customer->customer_id,
            'slot_id' => $this->slot->slot_id,
            'booking_date' => '2026-09-25',
            'booking_time' => '18:00',
            'party_size' => 4,
        ]);

        $this->actingAs($this->waitstaff, 'staff')
            ->withSession(['staff_last_activity' => now()])
            ->post(route('staff.reservations.approve', $reservation))
            ->assertRedirect()
            ->assertSessionHas('status', 'reservation-approved');

        $reservation->refresh();
        $this->assertSame(ReservationStatus::Confirmed, $reservation->status);
        $this->assertSame($this->waitstaff->staff_id, $reservation->reviewed_by_staff_id);
    }

    public function test_staff_can_decline_requested_reservation_via_post(): void
    {
        $reservation = Reservation::factory()->create([
            'customer_id' => $this->customer->customer_id,
            'slot_id' => $this->slot->slot_id,
            'booking_date' => '2026-09-25',
            'booking_time' => '18:00',
            'party_size' => 4,
        ]);

        $this->actingAs($this->waitstaff, 'staff')
            ->withSession(['staff_last_activity' => now()])
            ->post(route('staff.reservations.decline', $reservation), [
                'decline_reason' => 'Private function booked',
            ])
            ->assertRedirect()
            ->assertSessionHas('status', 'reservation-declined');

        $reservation->refresh();
        $this->assertSame(ReservationStatus::Declined, $reservation->status);
        $this->assertSame('Private function booked', $reservation->decline_reason);
        $this->assertSame($this->waitstaff->staff_id, $reservation->reviewed_by_staff_id);
    }

    public function test_trust_profile_endpoint_returns_json_profile(): void
    {
        $this->actingAs($this->waitstaff, 'staff')
            ->withSession(['staff_last_activity' => now()])
            ->getJson(route('staff.customers.trust', $this->customer))
            ->assertOk()
            ->assertJson([
                'customer_id' => $this->customer->customer_id,
                'customer_name' => 'Jane Smith',
                'badge' => 'New',
                'completed_visits_count' => 0,
            ]);
    }
}
