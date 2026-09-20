<?php

namespace Tests\Feature\Public;

use App\Models\Customer;
use App\Models\Reservation;
use App\Models\Setting;
use App\Models\SlotCapacity;
use App\Services\SettingService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Task 68: Public Availability API endpoint tests (FR61, BR32-BR35, BR58, TC-UC13-02, TC-UC13-03).
 */
class AvailabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_availability_endpoint_returns_json_with_rules_and_slots(): void
    {
        $slot1 = SlotCapacity::factory()->create(['slot_time' => '18:00', 'max_covers' => 30]);
        $slot2 = SlotCapacity::factory()->create(['slot_time' => '19:00', 'max_covers' => 40]);

        $futureTuesday = Carbon::now('Australia/Melbourne')->addDays(10)->next(Carbon::TUESDAY)->toDateString();

        $response = $this->getJson(route('reservations.availability', [
            'date' => $futureTuesday,
            'party_size' => 4,
        ]));

        $response->assertOk()
            ->assertJsonPath('online_enabled', true)
            ->assertJsonPath('closed_weekdays', [1])
            ->assertJsonPath('max_days_ahead', 60)
            ->assertJsonPath('max_party_online', 10)
            ->assertJsonPath('min_lead_hours', 2)
            ->assertJsonPath('availability.status', 'available')
            ->assertJsonPath('availability.date', $futureTuesday)
            ->assertJsonPath('availability.party_size', 4)
            ->assertJsonPath('availability.duration_minutes', 120); // 4 guests -> 120 min

        $slots = $response->json('availability.slots');
        $this->assertCount(2, $slots);
        $this->assertTrue($slots[0]['is_available']);
        $this->assertSame(30, $slots[0]['remaining_covers']);
    }

    public function test_availability_endpoint_reports_closed_when_date_is_closed_weekday(): void
    {
        $nextMonday = Carbon::now('Australia/Melbourne')->next(Carbon::MONDAY)->toDateString();

        $response = $this->getJson(route('reservations.availability', [
            'date' => $nextMonday,
            'party_size' => 2,
        ]));

        $response->assertOk()
            ->assertJsonPath('availability.status', 'closed')
            ->assertJsonPath('availability.slots', []);
    }

    public function test_availability_endpoint_reports_paused_when_online_disabled(): void
    {
        Setting::updateOrCreate(
            ['setting_key' => 'reservations_online_enabled'],
            ['setting_value' => '0', 'value_type' => 'bool']
        );
        app(SettingService::class)->clearCache();

        $futureDate = Carbon::now('Australia/Melbourne')->addDays(5)->next(Carbon::WEDNESDAY)->toDateString();

        $response = $this->getJson(route('reservations.availability', [
            'date' => $futureDate,
            'party_size' => 2,
        ]));

        $response->assertOk()
            ->assertJsonPath('online_enabled', false)
            ->assertJsonPath('availability.status', 'paused');
    }

    public function test_availability_endpoint_reports_party_too_large(): void
    {
        $futureDate = Carbon::now('Australia/Melbourne')->addDays(5)->next(Carbon::WEDNESDAY)->toDateString();

        $response = $this->getJson(route('reservations.availability', [
            'date' => $futureDate,
            'party_size' => 15, // default online max is 10
        ]));

        $response->assertOk()
            ->assertJsonPath('availability.status', 'party_too_large');
    }

    public function test_availability_endpoint_reflects_capacity_drop_after_booking(): void
    {
        $slot = SlotCapacity::factory()->create(['slot_time' => '18:00', 'max_covers' => 10]);
        $customer = Customer::factory()->create();
        $date = Carbon::now('Australia/Melbourne')->addDays(8)->next(Carbon::THURSDAY)->toDateString();

        // 8 covers booked out of 10
        Reservation::create([
            'customer_id' => $customer->customer_id,
            'slot_id' => $slot->slot_id,
            'reference_code' => 'CR-CAP1',
            'booking_date' => $date,
            'booking_time' => '18:00',
            'party_size' => 8,
        ]);

        // Party of 2 should still fit (10 - 8 = 2 remaining)
        $response = $this->getJson(route('reservations.availability', [
            'date' => $date,
            'party_size' => 2,
        ]));

        $response->assertOk();
        $slotData = $response->json('availability.slots.0');
        $this->assertSame(2, $slotData['remaining_covers']);
        $this->assertTrue($slotData['is_available']);

        // Party of 3 should NOT fit
        $response3 = $this->getJson(route('reservations.availability', [
            'date' => $date,
            'party_size' => 3,
        ]));

        $response3->assertOk();
        $slotData3 = $response3->json('availability.slots.0');
        $this->assertSame(2, $slotData3['remaining_covers']);
        $this->assertFalse($slotData3['is_available']);
        $this->assertSame('capacity', $slotData3['unavailable_reason']);
    }
}
