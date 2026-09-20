<?php

namespace Tests\Feature\Services;

use App\Models\Customer;
use App\Models\Reservation;
use App\Models\Setting;
use App\Models\SlotCapacity;
use App\Services\AvailabilityService;
use App\Services\SettingService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Task 68: AvailabilityService (FR61, BR32, BR33, BR34, BR35, BR58).
 */
class AvailabilityServiceTest extends TestCase
{
    use RefreshDatabase;

    private AvailabilityService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(AvailabilityService::class);
    }

    public function test_online_reservations_enabled_reflects_setting(): void
    {
        $this->assertTrue($this->service->isOnlineReservationsEnabled());

        Setting::updateOrCreate(
            ['setting_key' => 'reservations_online_enabled'],
            ['setting_value' => '0', 'value_type' => 'bool']
        );
        app(SettingService::class)->clearCache();

        $this->assertFalse($this->service->isOnlineReservationsEnabled());
    }

    public function test_closed_weekdays_detection_identifies_mondays(): void
    {
        // Monday = 1 in ISO-8601
        $this->assertEquals([1], $this->service->getClosedWeekdays());

        // Find next Monday
        $nextMonday = Carbon::now('Australia/Melbourne')->next(Carbon::MONDAY);
        $nextTuesday = Carbon::now('Australia/Melbourne')->next(Carbon::TUESDAY);

        $this->assertTrue($this->service->isClosedWeekday($nextMonday));
        $this->assertFalse($this->service->isClosedWeekday($nextTuesday));
    }

    public function test_duration_minutes_by_party_size(): void
    {
        // 1-2 guests: 90 mins (BR34)
        $this->assertSame(90, $this->service->getDurationMinutes(1));
        $this->assertSame(90, $this->service->getDurationMinutes(2));

        // 3-6 guests: 120 mins (BR34)
        $this->assertSame(120, $this->service->getDurationMinutes(3));
        $this->assertSame(120, $this->service->getDurationMinutes(6));

        // 7+ guests: 150 mins (BR34)
        $this->assertSame(150, $this->service->getDurationMinutes(7));
        $this->assertSame(150, $this->service->getDurationMinutes(12));
    }

    public function test_lead_time_requires_at_least_two_hours(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-25 12:00:00', 'Australia/Melbourne'));

        $todayStr = '2026-09-25';

        // 1 hour ahead -> invalid (needs >= 2 hours, BR35)
        $this->assertFalse($this->service->isLeadTimeValid($todayStr, '13:00'));

        // 3 hours ahead -> valid
        $this->assertTrue($this->service->isLeadTimeValid($todayStr, '15:00'));

        // Future day -> valid
        $this->assertTrue($this->service->isLeadTimeValid('2026-09-26', '12:00'));

        Carbon::setTestNow();
    }

    public function test_date_within_window_checks_past_and_max_days(): void
    {
        $today = Carbon::now('Australia/Melbourne');

        // Yesterday -> false
        $this->assertFalse($this->service->isDateWithinWindow($today->copy()->subDay()));

        // Today & in 30 days -> true
        $this->assertTrue($this->service->isDateWithinWindow($today));
        $this->assertTrue($this->service->isDateWithinWindow($today->copy()->addDays(30)));
        $this->assertTrue($this->service->isDateWithinWindow($today->copy()->addDays(60)));

        // 61 days ahead -> false (max 60 days, BR35)
        $this->assertFalse($this->service->isDateWithinWindow($today->copy()->addDays(61)));
    }

    public function test_booked_covers_counts_requested_confirmed_and_seated(): void
    {
        $slot = SlotCapacity::factory()->create(['max_covers' => 30]);
        $customer = Customer::factory()->create();
        $date = Carbon::now('Australia/Melbourne')->addDays(5)->toDateString();

        // Requested: 4 covers
        $req = Reservation::create([
            'customer_id' => $customer->customer_id,
            'slot_id' => $slot->slot_id,
            'reference_code' => 'CR-TEST1',
            'booking_date' => $date,
            'booking_time' => '18:00',
            'party_size' => 4,
        ]);

        // Confirmed: 6 covers
        $conf = Reservation::create([
            'customer_id' => $customer->customer_id,
            'slot_id' => $slot->slot_id,
            'reference_code' => 'CR-TEST2',
            'booking_date' => $date,
            'booking_time' => '18:00',
            'party_size' => 6,
        ]);
        $conf->forceFill(['status' => 'confirmed'])->save();

        // Seated: 2 covers
        $seated = Reservation::create([
            'customer_id' => $customer->customer_id,
            'slot_id' => $slot->slot_id,
            'reference_code' => 'CR-TEST3',
            'booking_date' => $date,
            'booking_time' => '18:00',
            'party_size' => 2,
        ]);
        $seated->forceFill(['status' => 'seated'])->save();

        // Cancelled & Declined & Expired: should NOT count
        $canc = Reservation::create([
            'customer_id' => $customer->customer_id,
            'slot_id' => $slot->slot_id,
            'reference_code' => 'CR-TEST4',
            'booking_date' => $date,
            'booking_time' => '18:00',
            'party_size' => 8,
        ]);
        $canc->forceFill(['status' => 'cancelled'])->save();

        // Total counted covers should be 4 + 6 + 2 = 12 (BR32)
        $booked = $this->service->getBookedCovers($slot->slot_id, $date);
        $this->assertSame(12, $booked);

        // Capacity checks (BR32, BR33)
        // 30 - 12 = 18 remaining. Party of 18 fits, party of 19 does not.
        $this->assertTrue($this->service->hasSlotCapacity($slot, $date, 18));
        $this->assertFalse($this->service->hasSlotCapacity($slot, $date, 19));
    }

    public function test_check_date_availability_validates_business_rules(): void
    {
        $slot = SlotCapacity::factory()->create(['slot_time' => '19:00', 'max_covers' => 20]);
        $today = Carbon::now('Australia/Melbourne');

        // Past date
        $res = $this->service->checkDateAvailability($today->copy()->subDay()->toDateString(), 2);
        $this->assertSame('past_date', $res['status']);

        // Closed weekday (Monday)
        $monday = $today->copy()->next(Carbon::MONDAY);
        $res = $this->service->checkDateAvailability($monday->toDateString(), 2);
        $this->assertSame('closed', $res['status']);

        // Party exceeding max online (default 10, BR35)
        $tuesday = $today->copy()->next(Carbon::TUESDAY);
        $res = $this->service->checkDateAvailability($tuesday->toDateString(), 11);
        $this->assertSame('party_too_large', $res['status']);

        // Online paused (BR58)
        Setting::updateOrCreate(['setting_key' => 'reservations_online_enabled'], ['setting_value' => '0', 'value_type' => 'bool']);
        app(SettingService::class)->clearCache();
        $res = $this->service->checkDateAvailability($tuesday->toDateString(), 2);
        $this->assertSame('paused', $res['status']);
    }
}
