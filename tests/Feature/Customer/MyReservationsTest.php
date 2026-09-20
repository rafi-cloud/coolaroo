<?php

namespace Tests\Feature\Customer;

use App\Enums\ReservationStatus;
use App\Models\Customer;
use App\Models\Reservation;
use App\Models\SlotCapacity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class MyReservationsTest extends TestCase
{
    use RefreshDatabase;

    private Customer $customer;
    private SlotCapacity $slot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\SettingSeeder::class);

        // Fixed test time: Tuesday 22 Sep 2026 12:00:00 (Tuesday is an open day)
        Carbon::setTestNow(Carbon::parse('2026-09-22 12:00:00', 'Australia/Melbourne'));

        $this->slot = SlotCapacity::factory()->create([
            'slot_time' => '18:00',
            'max_covers' => 20,
            'is_active' => true,
        ]);

        $this->customer = Customer::factory()->create([
            'email_verified_at' => now(),
            'phone' => '0412345678',
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_guest_is_redirected_to_customer_login(): void
    {
        $response = $this->get(route('reservations.index'));

        $response->assertRedirect(route('customer.login'));
    }

    public function test_customer_can_view_upcoming_and_past_reservations(): void
    {
        $upcoming = Reservation::factory()->confirmed()->create([
            'customer_id' => $this->customer->customer_id,
            'slot_id' => $this->slot->slot_id,
            'reference_code' => 'CR-UPCOMING1',
            'booking_date' => '2026-09-25',
            'booking_time' => '18:00',
            'party_size' => 4,
        ]);

        $past = Reservation::factory()->create([
            'customer_id' => $this->customer->customer_id,
            'slot_id' => $this->slot->slot_id,
            'reference_code' => 'CR-PAST1',
            'booking_date' => '2026-09-15',
            'booking_time' => '18:00',
            'party_size' => 2,
            'status' => ReservationStatus::Completed,
        ]);

        $response = $this->actingAs($this->customer, 'customer')
            ->get(route('reservations.index'));

        $response->assertOk()
            ->assertSee('CR-UPCOMING1')
            ->assertSee('CR-PAST1')
            ->assertSee('Confirmed')
            ->assertSee('Completed');
    }

    public function test_customer_can_update_special_requests(): void
    {
        $reservation = Reservation::factory()->confirmed()->create([
            'customer_id' => $this->customer->customer_id,
            'slot_id' => $this->slot->slot_id,
            'reference_code' => 'CR-REQ1',
            'booking_date' => '2026-09-25',
            'booking_time' => '18:00',
            'party_size' => 2,
            'special_requests' => 'None',
        ]);

        $response = $this->actingAs($this->customer, 'customer')
            ->patch(route('reservations.update', $reservation), [
                'special_requests' => 'Quiet table near window, anniversary',
            ]);

        $response->assertRedirect(route('reservations.index'))
            ->assertSessionHas('status', 'reservation-updated');

        $reservation->refresh();
        $this->assertSame('Quiet table near window, anniversary', $reservation->special_requests);
        $this->assertSame(ReservationStatus::Confirmed, $reservation->status);
    }

    public function test_customer_can_update_party_size_ahead_of_time_and_reverts_to_requested(): void
    {
        $reservation = Reservation::factory()->confirmed()->create([
            'customer_id' => $this->customer->customer_id,
            'slot_id' => $this->slot->slot_id,
            'reference_code' => 'CR-EDIT1',
            'booking_date' => '2026-09-25',
            'booking_time' => '18:00',
            'party_size' => 2,
        ]);

        $response = $this->actingAs($this->customer, 'customer')
            ->patch(route('reservations.update', $reservation), [
                'party_size' => 4,
            ]);

        $response->assertRedirect(route('reservations.index'))
            ->assertSessionHas('status', 'reservation-updated');

        $reservation->refresh();
        $this->assertSame(4, $reservation->party_size);
        $this->assertSame(ReservationStatus::Requested, $reservation->status); // BR36: reverted to requested
    }

    public function test_customer_cannot_update_core_details_within_2_hours(): void
    {
        // Booking today at 13:00 (1 hour away from test now 12:00)
        $slot13 = SlotCapacity::factory()->create(['slot_time' => '13:00', 'max_covers' => 20]);
        $reservation = Reservation::factory()->confirmed()->create([
            'customer_id' => $this->customer->customer_id,
            'slot_id' => $slot13->slot_id,
            'booking_date' => '2026-09-22',
            'booking_time' => '13:00',
            'party_size' => 2,
        ]);

        $response = $this->actingAs($this->customer, 'customer')
            ->patch(route('reservations.update', $reservation), [
                'party_size' => 3,
            ]);

        $response->assertSessionHasErrors('booking');

        $reservation->refresh();
        $this->assertSame(2, $reservation->party_size);
    }

    public function test_customer_can_cancel_ahead_of_time(): void
    {
        $reservation = Reservation::factory()->confirmed()->create([
            'customer_id' => $this->customer->customer_id,
            'slot_id' => $this->slot->slot_id,
            'reference_code' => 'CR-CANCEL1',
            'booking_date' => '2026-09-25',
            'booking_time' => '18:00',
            'party_size' => 2,
        ]);

        $response = $this->actingAs($this->customer, 'customer')
            ->post(route('reservations.cancel', $reservation));

        $response->assertRedirect(route('reservations.index'))
            ->assertSessionHas('status', 'reservation-cancelled');

        $reservation->refresh();
        $this->assertSame(ReservationStatus::Cancelled, $reservation->status);
        $this->assertFalse($reservation->is_late_cancellation);
        $this->assertSame('customer', $reservation->cancelled_by);
    }

    public function test_customer_cancelling_within_2_hours_marks_late_cancellation(): void
    {
        // 1 hour away
        $slot13 = SlotCapacity::factory()->create(['slot_time' => '13:00', 'max_covers' => 20]);
        $reservation = Reservation::factory()->confirmed()->create([
            'customer_id' => $this->customer->customer_id,
            'slot_id' => $slot13->slot_id,
            'reference_code' => 'CR-LATE1',
            'booking_date' => '2026-09-22',
            'booking_time' => '13:00',
            'party_size' => 2,
        ]);

        $response = $this->actingAs($this->customer, 'customer')
            ->post(route('reservations.cancel', $reservation));

        $response->assertRedirect(route('reservations.index'));

        $reservation->refresh();
        $this->assertSame(ReservationStatus::Cancelled, $reservation->status);
        $this->assertTrue($reservation->is_late_cancellation);
    }

    public function test_customer_cannot_update_or_cancel_another_customers_reservation(): void
    {
        $otherCustomer = Customer::factory()->create();
        $reservation = Reservation::factory()->confirmed()->create([
            'customer_id' => $otherCustomer->customer_id,
            'slot_id' => $this->slot->slot_id,
        ]);

        $updateResponse = $this->actingAs($this->customer, 'customer')
            ->patch(route('reservations.update', $reservation), [
                'special_requests' => 'Hacked',
            ]);

        $updateResponse->assertForbidden();

        $cancelResponse = $this->actingAs($this->customer, 'customer')
            ->post(route('reservations.cancel', $reservation));

        $cancelResponse->assertForbidden();
    }
}
