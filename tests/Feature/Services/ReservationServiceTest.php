<?php

namespace Tests\Feature\Services;

use App\Enums\ReservationStatus;
use App\Enums\TableStatus;
use App\Enums\VisitCloseReason;
use App\Exceptions\InvalidTransitionException;
use App\Models\Customer;
use App\Models\Reservation;
use App\Models\RestaurantTable;
use App\Models\Role;
use App\Models\Setting;
use App\Models\SlotCapacity;
use App\Models\Staff;
use App\Services\ReservationService;
use App\Services\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ReservationServiceTest extends TestCase
{
    use RefreshDatabase;

    private ReservationService $service;

    private SlotCapacity $slot;

    private Customer $customer;

    private Staff $staff;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-09-22 12:00:00', 'Australia/Melbourne'));

        $this->service = app(ReservationService::class);

        $this->slot = SlotCapacity::factory()->create([
            'slot_time' => '18:00',
            'max_covers' => 20,
            'is_active' => true,
        ]);

        $this->customer = Customer::factory()->create([
            'email_verified_at' => now(),
            'phone' => '0412345678',
        ]);

        $waitstaffRole = Role::firstOrCreate(['role_name' => 'waitstaff'], [
            'description' => 'Waitstaff',
            'landing_screen' => 'staff.floor.index',
        ]);

        $this->staff = Staff::factory()->create([
            'role_id' => $waitstaffRole->role_id,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_customer_can_request_reservation_with_valid_details_and_capacity(): void
    {
        $reservation = $this->service->request($this->customer, [
            'booking_date' => '2026-09-25',
            'booking_time' => '18:00',
            'party_size' => 4,
            'special_requests' => 'Window seat please',
        ]);

        $this->assertInstanceOf(Reservation::class, $reservation);
        $this->assertSame(ReservationStatus::Requested, $reservation->status);
        $this->assertSame('2026-09-25', $reservation->booking_date->format('Y-m-d'));
        $this->assertSame('18:00', substr($reservation->booking_time, 0, 5));
        $this->assertSame(4, $reservation->party_size);
        $this->assertSame('Window seat please', $reservation->special_requests);
        $this->assertStringStartsWith('CR-', $reservation->reference_code);

        $this->assertDatabaseHas('audit_log', [
            'customer_id' => $this->customer->customer_id,
            'action_type' => 'reservation_requested',
            'entity_id' => $reservation->reservation_id,
        ]);
    }

    public function test_request_blocked_when_online_reservations_paused(): void
    {
        Setting::updateOrCreate(
            ['setting_key' => 'reservations_online_enabled'],
            ['setting_value' => '0', 'value_type' => 'bool']
        );
        app(SettingService::class)->clearCache();

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Online reservations are currently paused');

        $this->service->request($this->customer, [
            'booking_date' => '2026-09-25',
            'slot_id' => $this->slot->slot_id,
            'party_size' => 2,
        ]);
    }

    public function test_request_blocked_without_verified_email(): void
    {
        $unverified = Customer::factory()->create([
            'email_verified_at' => null,
            'phone' => '0498765432',
        ]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('email address must be verified');

        $this->service->request($unverified, [
            'booking_date' => '2026-09-25',
            'slot_id' => $this->slot->slot_id,
            'party_size' => 2,
        ]);
    }

    public function test_request_blocked_without_phone_number(): void
    {
        $noPhone = Customer::factory()->create([
            'email_verified_at' => now(),
            'phone' => null,
        ]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('mobile phone number is required');

        $this->service->request($noPhone, [
            'booking_date' => '2026-09-25',
            'slot_id' => $this->slot->slot_id,
            'party_size' => 2,
        ]);
    }

    public function test_request_blocked_on_closed_weekday(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('venue is closed on this day');

        $this->service->request($this->customer, [
            'booking_date' => '2026-09-28',
            'slot_id' => $this->slot->slot_id,
            'party_size' => 2,
        ]);
    }

    public function test_request_blocked_with_less_than_2_hours_lead_time(): void
    {
        $earlySlot = SlotCapacity::factory()->create([
            'slot_time' => '13:00',
            'max_covers' => 20,
            'is_active' => true,
        ]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('hours lead time');

        $this->service->request($this->customer, [
            'booking_date' => '2026-09-22',
            'slot_id' => $earlySlot->slot_id,
            'party_size' => 2,
        ]);
    }

    public function test_request_blocked_when_party_size_exceeds_max_online(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Online bookings are limited to 10 guests');

        $this->service->request($this->customer, [
            'booking_date' => '2026-09-25',
            'slot_id' => $this->slot->slot_id,
            'party_size' => 12,
        ]);
    }

    public function test_request_blocked_when_slot_has_no_capacity(): void
    {
        Reservation::factory()->create([
            'slot_id' => $this->slot->slot_id,
            'booking_date' => '2026-09-25',
            'booking_time' => '18:00',
            'party_size' => 18,
        ]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('No availability for the selected time slot');

        $this->service->request($this->customer, [
            'booking_date' => '2026-09-25',
            'slot_id' => $this->slot->slot_id,
            'party_size' => 4,
        ]);
    }

    public function test_staff_can_approve_requested_reservation(): void
    {
        $reservation = Reservation::factory()->create([
            'customer_id' => $this->customer->customer_id,
            'slot_id' => $this->slot->slot_id,
            'booking_date' => '2026-09-25',
            'booking_time' => '18:00',
            'party_size' => 4,
        ]);

        $approved = $this->service->approve($reservation, $this->staff);

        $this->assertSame(ReservationStatus::Confirmed, $approved->status);
        $this->assertSame($this->staff->staff_id, $approved->reviewed_by_staff_id);
        $this->assertNotNull($approved->reviewed_at);

        $this->assertDatabaseHas('audit_log', [
            'staff_id' => $this->staff->staff_id,
            'action_type' => 'reservation_approved',
            'entity_id' => $reservation->reservation_id,
        ]);
    }

    public function test_staff_can_decline_requested_reservation_with_reason(): void
    {
        $reservation = Reservation::factory()->create([
            'customer_id' => $this->customer->customer_id,
            'slot_id' => $this->slot->slot_id,
            'booking_date' => '2026-09-25',
            'booking_time' => '18:00',
            'party_size' => 4,
        ]);

        $declined = $this->service->decline($reservation, $this->staff, 'Fully booked for private event');

        $this->assertSame(ReservationStatus::Declined, $declined->status);
        $this->assertSame('Fully booked for private event', $declined->decline_reason);
        $this->assertSame($this->staff->staff_id, $declined->reviewed_by_staff_id);
        $this->assertNotNull($declined->reviewed_at);

        $this->assertDatabaseHas('audit_log', [
            'staff_id' => $this->staff->staff_id,
            'action_type' => 'reservation_declined',
            'entity_id' => $reservation->reservation_id,
        ]);
    }

    public function test_customer_can_cancel_early_without_late_flag(): void
    {
        $reservation = Reservation::factory()->confirmed()->create([
            'customer_id' => $this->customer->customer_id,
            'slot_id' => $this->slot->slot_id,
            'booking_date' => '2026-09-25',
            'booking_time' => '18:00',
            'party_size' => 4,
        ]);

        $cancelled = $this->service->cancelByCustomer($reservation, $this->customer);

        $this->assertSame(ReservationStatus::Cancelled, $cancelled->status);
        $this->assertSame('customer', $cancelled->cancelled_by);
        $this->assertFalse($cancelled->is_late_cancellation);
        $this->assertNotNull($cancelled->cancelled_at);
    }

    public function test_customer_cancelling_within_2_hours_marks_late_cancellation_and_unlinks_tables(): void
    {
        $slot13 = SlotCapacity::factory()->create(['slot_time' => '13:00', 'max_covers' => 20]);
        $reservation = Reservation::factory()->confirmed()->create([
            'customer_id' => $this->customer->customer_id,
            'slot_id' => $slot13->slot_id,
            'booking_date' => '2026-09-22',
            'booking_time' => '13:00',
            'party_size' => 2,
        ]);

        $table = RestaurantTable::factory()->create();
        $table->forceFill(['status' => TableStatus::Reserved])->save();
        $visit = $table->visits()->create([
            'reservation_id' => $reservation->reservation_id,
            'guest_count' => 2,
        ]);

        $cancelled = $this->service->cancelByCustomer($reservation, $this->customer);

        $this->assertSame(ReservationStatus::Cancelled, $cancelled->status);
        $this->assertTrue($cancelled->is_late_cancellation);
        $this->assertSame('customer', $cancelled->cancelled_by);

        $table->refresh();
        $this->assertSame(TableStatus::Available, $table->status);

        $visit->refresh();
        $this->assertNotNull($visit->closed_at);
        $this->assertSame(VisitCloseReason::Cancelled, $visit->close_reason);
    }

    public function test_staff_can_cancel_reservation(): void
    {
        $reservation = Reservation::factory()->confirmed()->create([
            'customer_id' => $this->customer->customer_id,
            'slot_id' => $this->slot->slot_id,
            'booking_date' => '2026-09-25',
            'booking_time' => '18:00',
            'party_size' => 2,
        ]);

        $cancelled = $this->service->cancelByStaff($reservation, $this->staff, 'Kitchen emergency');

        $this->assertSame(ReservationStatus::Cancelled, $cancelled->status);
        $this->assertSame('staff', $cancelled->cancelled_by);
        $this->assertSame('Kitchen emergency', $cancelled->decline_reason);
    }

    public function test_customer_can_update_special_requests_within_2_hours(): void
    {
        $slot13 = SlotCapacity::factory()->create(['slot_time' => '13:00', 'max_covers' => 20]);
        $reservation = Reservation::factory()->confirmed()->create([
            'customer_id' => $this->customer->customer_id,
            'slot_id' => $slot13->slot_id,
            'booking_date' => '2026-09-22',
            'booking_time' => '13:00',
            'party_size' => 2,
            'special_requests' => 'Quiet corner',
        ]);

        $updated = $this->service->update($reservation, $this->customer, [
            'special_requests' => 'Quiet corner, bringing birthday cake',
        ]);

        $this->assertSame('Quiet corner, bringing birthday cake', $updated->special_requests);
        $this->assertSame(ReservationStatus::Confirmed, $updated->status);
    }

    public function test_customer_core_change_blocked_within_2_hours(): void
    {
        $slot13 = SlotCapacity::factory()->create(['slot_time' => '13:00', 'max_covers' => 20]);
        $reservation = Reservation::factory()->confirmed()->create([
            'customer_id' => $this->customer->customer_id,
            'slot_id' => $slot13->slot_id,
            'booking_date' => '2026-09-22',
            'booking_time' => '13:00',
            'party_size' => 2,
        ]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('cannot be changed within 2 hours');

        $this->service->update($reservation, $this->customer, [
            'party_size' => 3,
        ]);
    }

    public function test_customer_core_change_reverts_confirmed_to_requested_and_unlinks_tables(): void
    {
        $reservation = Reservation::factory()->confirmed()->create([
            'customer_id' => $this->customer->customer_id,
            'slot_id' => $this->slot->slot_id,
            'booking_date' => '2026-09-25',
            'booking_time' => '18:00',
            'party_size' => 2,
        ]);

        $table = RestaurantTable::factory()->create();
        $table->forceFill(['status' => TableStatus::Reserved])->save();
        $visit = $table->visits()->create([
            'reservation_id' => $reservation->reservation_id,
            'guest_count' => 2,
        ]);

        $updated = $this->service->update($reservation, $this->customer, [
            'party_size' => 4,
        ]);

        $this->assertSame(ReservationStatus::Requested, $updated->status);
        $this->assertSame(4, $updated->party_size);

        $table->refresh();
        $this->assertSame(TableStatus::Available, $table->status);

        $visit->refresh();
        $this->assertNotNull($visit->closed_at);
        $this->assertSame(VisitCloseReason::Unassigned, $visit->close_reason);
    }

    public function test_staff_can_update_core_details_within_2_hours(): void
    {
        $slot13 = SlotCapacity::factory()->create(['slot_time' => '13:00', 'max_covers' => 20]);
        $reservation = Reservation::factory()->confirmed()->create([
            'customer_id' => $this->customer->customer_id,
            'slot_id' => $slot13->slot_id,
            'booking_date' => '2026-09-22',
            'booking_time' => '13:00',
            'party_size' => 2,
        ]);

        $updated = $this->service->update($reservation, $this->staff, [
            'party_size' => 3,
        ]);

        $this->assertSame(3, $updated->party_size);
        $this->assertSame(ReservationStatus::Confirmed, $updated->status);
    }

    public function test_invalid_status_transition_throws_exception(): void
    {
        $reservation = Reservation::factory()->create([
            'customer_id' => $this->customer->customer_id,
            'slot_id' => $this->slot->slot_id,
            'booking_date' => '2026-09-25',
            'booking_time' => '18:00',
            'party_size' => 2,
        ]);

        $this->service->decline($reservation, $this->staff, 'No room');

        $this->expectException(InvalidTransitionException::class);
        $this->service->approve($reservation, $this->staff);
    }
}
