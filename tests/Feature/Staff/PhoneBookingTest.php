<?php

namespace Tests\Feature\Staff;

use App\Enums\ReservationStatus;
use App\Models\Customer;
use App\Models\Reservation;
use App\Models\Role;
use App\Models\Setting;
use App\Models\SlotCapacity;
use App\Models\Staff;
use App\Services\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PhoneBookingTest extends TestCase
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
            'full_name' => 'Sarah Connor',
            'email' => 'sarah@example.com',
            'phone' => '0422334455',
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_waitstaff_can_create_phone_booking_for_guest_confirmed_immediately(): void
    {
        $response = $this->actingAs($this->waitstaff, 'staff')
            ->withSession(['staff_last_activity' => now()])
            ->post(route('staff.reservations.store'), [
                'guest_name' => 'John Doe',
                'guest_phone' => '0400111222',
                'booking_date' => '2026-09-25',
                'slot_id' => $this->slot->slot_id,
                'party_size' => 4,
                'special_requests' => 'Window booth requested via phone',
            ]);

        $response->assertRedirect(route('staff.reservations.index', ['date' => '2026-09-25']))
            ->assertSessionHas('status', 'reservation-created');

        $reservation = Reservation::where('guest_name', 'John Doe')->first();
        $this->assertNotNull($reservation);
        $this->assertSame(ReservationStatus::Confirmed, $reservation->status);
        $this->assertSame('0400111222', $reservation->guest_phone);
        $this->assertNull($reservation->customer_id);
        $this->assertSame($this->waitstaff->staff_id, $reservation->created_by_staff_id);
        $this->assertSame($this->waitstaff->staff_id, $reservation->reviewed_by_staff_id);
        $this->assertNotNull($reservation->reviewed_at);
        $this->assertStringStartsWith('CR-', $reservation->reference_code);

        $this->assertDatabaseHas('audit_log', [
            'staff_id' => $this->waitstaff->staff_id,
            'action_type' => 'reservation_phone_created',
            'entity_id' => $reservation->reservation_id,
        ]);
    }

    public function test_waitstaff_can_create_phone_booking_for_existing_customer(): void
    {
        $response = $this->actingAs($this->waitstaff, 'staff')
            ->withSession(['staff_last_activity' => now()])
            ->post(route('staff.reservations.store'), [
                'customer_id' => $this->customer->customer_id,
                'booking_date' => '2026-09-25',
                'slot_id' => $this->slot->slot_id,
                'party_size' => 2,
            ]);

        $response->assertRedirect(route('staff.reservations.index', ['date' => '2026-09-25']))
            ->assertSessionHas('status', 'reservation-created');

        $reservation = Reservation::where('customer_id', $this->customer->customer_id)->first();
        $this->assertNotNull($reservation);
        $this->assertSame(ReservationStatus::Confirmed, $reservation->status);
        $this->assertSame($this->waitstaff->staff_id, $reservation->created_by_staff_id);
    }

    public function test_phone_booking_allowed_when_online_reservations_paused(): void
    {
        // Pause online reservations per BR58
        Setting::updateOrCreate(
            ['setting_key' => 'reservations_online_enabled'],
            ['setting_value' => '0', 'value_type' => 'bool']
        );
        app(SettingService::class)->clearCache();

        $response = $this->actingAs($this->waitstaff, 'staff')
            ->withSession(['staff_last_activity' => now()])
            ->post(route('staff.reservations.store'), [
                'guest_name' => 'Robert Paulson',
                'guest_phone' => '0433221100',
                'booking_date' => '2026-09-25',
                'slot_id' => $this->slot->slot_id,
                'party_size' => 6,
            ]);

        $response->assertRedirect(route('staff.reservations.index', ['date' => '2026-09-25']))
            ->assertSessionHas('status', 'reservation-created');

        $this->assertDatabaseHas('reservation', [
            'guest_name' => 'Robert Paulson',
            'status' => 'confirmed',
        ]);
    }

    public function test_phone_booking_blocked_when_slot_capacity_exceeded(): void
    {
        // Slot has max_covers = 20. Fill 18 covers.
        Reservation::factory()->confirmed()->create([
            'slot_id' => $this->slot->slot_id,
            'booking_date' => '2026-09-25',
            'party_size' => 18,
        ]);

        // Attempting phone booking for 5 covers (18 + 5 = 23 > 20)
        $response = $this->actingAs($this->waitstaff, 'staff')
            ->withSession(['staff_last_activity' => now()])
            ->post(route('staff.reservations.store'), [
                'guest_name' => 'Overcapacity Guest',
                'guest_phone' => '0499887766',
                'booking_date' => '2026-09-25',
                'slot_id' => $this->slot->slot_id,
                'party_size' => 5,
            ]);

        $response->assertSessionHasErrors('slot');
        $this->assertDatabaseMissing('reservation', [
            'guest_name' => 'Overcapacity Guest',
        ]);
    }

    public function test_phone_booking_requires_either_customer_or_guest_details(): void
    {
        $response = $this->actingAs($this->waitstaff, 'staff')
            ->withSession(['staff_last_activity' => now()])
            ->post(route('staff.reservations.store'), [
                'customer_id' => null,
                'guest_name' => null,
                'guest_phone' => null,
                'booking_date' => '2026-09-25',
                'slot_id' => $this->slot->slot_id,
                'party_size' => 2,
            ]);

        $response->assertSessionHasErrors(['guest_name', 'guest_phone']);
    }

    public function test_kitchen_staff_cannot_create_phone_booking(): void
    {
        $this->actingAs($this->kitchen, 'staff')
            ->withSession(['staff_last_activity' => now()])
            ->post(route('staff.reservations.store'), [
                'guest_name' => 'Unauthorized Guest',
                'guest_phone' => '0411111111',
                'booking_date' => '2026-09-25',
                'slot_id' => $this->slot->slot_id,
                'party_size' => 2,
            ])
            ->assertForbidden();
    }

    public function test_admin_can_create_phone_booking_via_role_bypass(): void
    {
        $response = $this->actingAs($this->admin, 'staff')
            ->withSession(['staff_last_activity' => now()])
            ->post(route('staff.reservations.store'), [
                'guest_name' => 'Admin Guest',
                'guest_phone' => '0488888888',
                'booking_date' => '2026-09-25',
                'slot_id' => $this->slot->slot_id,
                'party_size' => 2,
            ]);

        $response->assertRedirect(route('staff.reservations.index', ['date' => '2026-09-25']))
            ->assertSessionHas('status', 'reservation-created');

        $this->assertDatabaseHas('reservation', [
            'guest_name' => 'Admin Guest',
            'created_by_staff_id' => $this->admin->staff_id,
            'status' => 'confirmed',
        ]);
    }
}
