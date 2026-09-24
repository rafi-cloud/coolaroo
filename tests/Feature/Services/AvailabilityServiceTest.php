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
        $this->assertEquals([1], $this->service->getClosedWeekdays());

        $nextMonday = Carbon::now('Australia/Melbourne')->next(Carbon::MONDAY);
        $nextTuesday = Carbon::now('Australia/Melbourne')->next(Carbon::TUESDAY);

        $this->assertTrue($this->service->isClosedWeekday($nextMonday));
        $this->assertFalse($this->service->isClosedWeekday($nextTuesday));
    }

    public function test_duration_minutes_by_party_size(): void
    {
        $this->assertSame(90, $this->service->getDurationMinutes(1));
        $this->assertSame(90, $this->service->getDurationMinutes(2));

        $this->assertSame(120, $this->service->getDurationMinutes(3));
        $this->assertSame(120, $this->service->getDurationMinutes(6));

        $this->assertSame(150, $this->service->getDurationMinutes(7));
        $this->assertSame(150, $this->service->getDurationMinutes(12));
    }

    public function test_lead_time_requires_at_least_two_hours(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-25 12:00:00', 'Australia/Melbourne'));

        $todayStr = '2026-09-25';

        $this->assertFalse($this->service->isLeadTimeValid($todayStr, '13:00'));

        $this->assertTrue($this->service->isLeadTimeValid($todayStr, '15:00'));

        $this->assertTrue($this->service->isLeadTimeValid('2026-09-26', '12:00'));

        Carbon::setTestNow();
    }

    public function test_date_within_window_checks_past_and_max_days(): void
    {
        $today = Carbon::now('Australia/Melbourne');

        $this->assertFalse($this->service->isDateWithinWindow($today->copy()->subDay()));

        $this->assertTrue($this->service->isDateWithinWindow($today));
        $this->assertTrue($this->service->isDateWithinWindow($today->copy()->addDays(30)));
        $this->assertTrue($this->service->isDateWithinWindow($today->copy()->addDays(60)));

        $this->assertFalse($this->service->isDateWithinWindow($today->copy()->addDays(61)));
    }

    public function test_booked_covers_counts_requested_confirmed_and_seated(): void
    {
        $slot = SlotCapacity::factory()->create(['max_covers' => 30]);
        $customer = Customer::factory()->create();
        $date = Carbon::now('Australia/Melbourne')->addDays(5)->toDateString();

        $req = Reservation::create([
            'customer_id' => $customer->customer_id,
            'slot_id' => $slot->slot_id,
            'reference_code' => 'CR-TEST1',
            'booking_date' => $date,
            'booking_time' => '18:00',
            'party_size' => 4,
        ]);

        $conf = Reservation::create([
            'customer_id' => $customer->customer_id,
            'slot_id' => $slot->slot_id,
            'reference_code' => 'CR-TEST2',
            'booking_date' => $date,
            'booking_time' => '18:00',
            'party_size' => 6,
        ]);
        $conf->forceFill(['status' => 'confirmed'])->save();

        $seated = Reservation::create([
            'customer_id' => $customer->customer_id,
            'slot_id' => $slot->slot_id,
            'reference_code' => 'CR-TEST3',
            'booking_date' => $date,
            'booking_time' => '18:00',
            'party_size' => 2,
        ]);
        $seated->forceFill(['status' => 'seated'])->save();

        $canc = Reservation::create([
            'customer_id' => $customer->customer_id,
            'slot_id' => $slot->slot_id,
            'reference_code' => 'CR-TEST4',
            'booking_date' => $date,
            'booking_time' => '18:00',
            'party_size' => 8,
        ]);
        $canc->forceFill(['status' => 'cancelled'])->save();

        $booked = $this->service->getBookedCovers($slot->slot_id, $date);
        $this->assertSame(12, $booked);

        $this->assertTrue($this->service->hasSlotCapacity($slot, $date, 18));
        $this->assertFalse($this->service->hasSlotCapacity($slot, $date, 19));
    }

    public function test_check_date_availability_validates_business_rules(): void
    {
        $slot = SlotCapacity::factory()->create(['slot_time' => '19:00', 'max_covers' => 20]);
        $today = Carbon::now('Australia/Melbourne');

        $res = $this->service->checkDateAvailability($today->copy()->subDay()->toDateString(), 2);
        $this->assertSame('past_date', $res['status']);

        $monday = $today->copy()->next(Carbon::MONDAY);
        $res = $this->service->checkDateAvailability($monday->toDateString(), 2);
        $this->assertSame('closed', $res['status']);

        $tuesday = $today->copy()->next(Carbon::TUESDAY);
        $res = $this->service->checkDateAvailability($tuesday->toDateString(), 11);
        $this->assertSame('party_too_large', $res['status']);

        Setting::updateOrCreate(['setting_key' => 'reservations_online_enabled'], ['setting_value' => '0', 'value_type' => 'bool']);
        app(SettingService::class)->clearCache();
        $res = $this->service->checkDateAvailability($tuesday->toDateString(), 2);
        $this->assertSame('paused', $res['status']);
    }
}
