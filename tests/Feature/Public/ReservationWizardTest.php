<?php

namespace Tests\Feature\Public;

use App\Enums\ReservationStatus;
use App\Models\Customer;
use App\Models\Reservation;
use App\Models\Setting;
use App\Models\SlotCapacity;
use App\Services\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ReservationWizardTest extends TestCase
{
    use RefreshDatabase;

    private SlotCapacity $slot;
    private Customer $customer;

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
            'full_name' => 'Wade Wilson',
            'email' => 'deadpool@example.com',
            'phone' => '0412345678',
            'email_verified_at' => now(),
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_homepage_renders_reservation_wizard_section(): void
    {
        $response = $this->get(route('home'));

        $response->assertOk()
            ->assertSee('data-testid="reservation-wizard-section"', false)
            ->assertSee('Book a table')
            ->assertSee('data-testid="wizard-step-1"', false)
            ->assertSee('data-testid="reserve-party-size-select"', false)
            ->assertSee('data-testid="reserve-date-input"', false);
    }

    public function test_authenticated_verified_customer_can_submit_reservation_request(): void
    {
        // Book for Friday 25 Sep 2026 at 18:00 (open day, within 60 days, > 2h lead time)
        $bookingDate = '2026-09-25';

        $response = $this->actingAs($this->customer, 'customer')
            ->post(route('reservations.store'), [
                'booking_date' => $bookingDate,
                'slot_id' => $this->slot->slot_id,
                'party_size' => 4,
                'special_requests' => 'Window seat requested, celebrating birthday',
            ]);

        $response->assertRedirect(route('home').'#reserve');
        $response->assertSessionHas('status', 'reservation-requested');
        $response->assertSessionHas('reservation_code');

        $reservation = Reservation::where('customer_id', $this->customer->customer_id)->first();
        $this->assertNotNull($reservation);
        $this->assertSame(ReservationStatus::Requested, $reservation->status);
        $this->assertSame(4, $reservation->party_size);
        $this->assertStringStartsWith('CR-', $reservation->reference_code);
        $this->assertSame('Window seat requested, celebrating birthday', $reservation->special_requests);
    }

    public function test_unauthenticated_guest_cannot_submit_reservation_request(): void
    {
        $response = $this->post(route('reservations.store'), [
            'booking_date' => '2026-09-25',
            'slot_id' => $this->slot->slot_id,
            'party_size' => 2,
        ]);

        $response->assertRedirect(route('customer.login'));
    }

    public function test_customer_without_verified_email_is_blocked(): void
    {
        $unverifiedCustomer = Customer::factory()->create([
            'email' => 'unverified@example.com',
            'phone' => '0499887766',
            'email_verified_at' => null,
        ]);

        $response = $this->actingAs($unverifiedCustomer, 'customer')
            ->post(route('reservations.store'), [
                'booking_date' => '2026-09-25',
                'slot_id' => $this->slot->slot_id,
                'party_size' => 2,
            ]);

        $response->assertRedirect(route('verification.notice'));
    }

    public function test_customer_without_mobile_phone_is_rejected(): void
    {
        $customerWithoutPhone = Customer::factory()->create([
            'email' => 'nophone@example.com',
            'phone' => null,
            'email_verified_at' => now(),
        ]);

        $response = $this->actingAs($customerWithoutPhone, 'customer')
            ->post(route('reservations.store'), [
                'booking_date' => '2026-09-25',
                'slot_id' => $this->slot->slot_id,
                'party_size' => 2,
            ]);

        $response->assertSessionHasErrors('phone');
    }

    public function test_past_date_or_closed_weekday_is_rejected(): void
    {
        // Monday 28 Sep 2026 is closed by default
        $mondayDate = '2026-09-28';

        $response = $this->actingAs($this->customer, 'customer')
            ->post(route('reservations.store'), [
                'booking_date' => $mondayDate,
                'slot_id' => $this->slot->slot_id,
                'party_size' => 2,
            ]);

        $response->assertSessionHasErrors('booking_date');

        // Past date
        $pastDate = '2026-09-20';
        $pastResponse = $this->actingAs($this->customer, 'customer')
            ->post(route('reservations.store'), [
                'booking_date' => $pastDate,
                'slot_id' => $this->slot->slot_id,
                'party_size' => 2,
            ]);

        $pastResponse->assertSessionHasErrors('booking_date');
    }

    public function test_when_online_reservations_paused_displays_pause_notice(): void
    {
        Setting::where('setting_key', 'reservations_online_enabled')->update(['setting_value' => '0']);
        app(SettingService::class)->clearCache();

        $response = $this->get(route('home'));

        $response->assertOk()
            ->assertSee('data-testid="reserve-paused-banner"', false)
            ->assertSee('Online reservations currently unavailable');

        $postResponse = $this->actingAs($this->customer, 'customer')
            ->post(route('reservations.store'), [
                'booking_date' => '2026-09-25',
                'slot_id' => $this->slot->slot_id,
                'party_size' => 2,
            ]);

        $postResponse->assertSessionHasErrors('booking');
    }
}
