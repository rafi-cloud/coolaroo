<?php

namespace Tests\Feature\Services;

use App\Enums\ReservationStatus;
use App\Models\Customer;
use App\Models\Reservation;
use App\Models\SlotCapacity;
use App\Services\TrustService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class TrustServiceTest extends TestCase
{
    use RefreshDatabase;

    private TrustService $trustService;
    private SlotCapacity $slot;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-09-22 12:00:00', 'Australia/Melbourne'));

        $this->trustService = app(TrustService::class);

        $this->slot = SlotCapacity::factory()->create([
            'slot_time' => '18:00',
            'max_covers' => 20,
            'is_active' => true,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_guest_without_customer_gets_new_badge(): void
    {
        $badge = $this->trustService->badge(null);
        $this->assertSame(TrustService::BADGE_NEW, $badge);

        $profile = $this->trustService->profile(null);
        $this->assertSame(TrustService::BADGE_NEW, $profile['badge']);
        $this->assertSame('Guest', $profile['customer_name']);
        $this->assertSame(0, $profile['completed_visits_count']);
    }

    public function test_customer_with_no_history_gets_new_badge(): void
    {
        $customer = Customer::factory()->create();

        $badge = $this->trustService->badge($customer);
        $this->assertSame(TrustService::BADGE_NEW, $badge);
    }

    public function test_customer_with_three_completed_visits_gets_regular_badge(): void
    {
        $customer = Customer::factory()->create();

        for ($i = 1; $i <= 3; $i++) {
            Reservation::factory()->create([
                'customer_id' => $customer->customer_id,
                'slot_id' => $this->slot->slot_id,
                'booking_date' => now()->subDays($i * 7)->toDateString(),
            ])->forceFill([
                'status' => ReservationStatus::Completed,
                'completed_at' => now()->subDays($i * 7),
            ])->save();
        }

        $badge = $this->trustService->badge($customer);
        $this->assertSame(TrustService::BADGE_REGULAR, $badge);
    }

    public function test_customer_with_one_uncleared_no_show_in_last_12_months_gets_flagged_badge(): void
    {
        $customer = Customer::factory()->create();

        Reservation::factory()->create([
            'customer_id' => $customer->customer_id,
            'slot_id' => $this->slot->slot_id,
            'booking_date' => now()->subMonths(3)->toDateString(),
        ])->forceFill([
            'status' => ReservationStatus::NoShow,
            'no_show_at' => now()->subMonths(3),
            'no_show_cleared_at' => null,
        ])->save();

        $badge = $this->trustService->badge($customer);
        $this->assertSame(TrustService::BADGE_FLAGGED, $badge);
    }

    public function test_flagged_badge_takes_precedence_over_regular_badge(): void
    {
        $customer = Customer::factory()->create();

        // 5 completed visits
        for ($i = 1; $i <= 5; $i++) {
            Reservation::factory()->create([
                'customer_id' => $customer->customer_id,
                'slot_id' => $this->slot->slot_id,
                'booking_date' => now()->subDays($i * 10)->toDateString(),
            ])->forceFill([
                'status' => ReservationStatus::Completed,
                'completed_at' => now()->subDays($i * 10),
            ])->save();
        }

        // 1 uncleared no-show
        Reservation::factory()->create([
            'customer_id' => $customer->customer_id,
            'slot_id' => $this->slot->slot_id,
            'booking_date' => now()->subMonths(1)->toDateString(),
        ])->forceFill([
            'status' => ReservationStatus::NoShow,
            'no_show_at' => now()->subMonths(1),
            'no_show_cleared_at' => null,
        ])->save();

        $badge = $this->trustService->badge($customer);
        $this->assertSame(TrustService::BADGE_FLAGGED, $badge);
    }

    public function test_cleared_no_show_does_not_result_in_flagged_badge(): void
    {
        $customer = Customer::factory()->create();

        Reservation::factory()->create([
            'customer_id' => $customer->customer_id,
            'slot_id' => $this->slot->slot_id,
            'booking_date' => now()->subMonths(2)->toDateString(),
        ])->forceFill([
            'status' => ReservationStatus::NoShow,
            'no_show_at' => now()->subMonths(2),
            'no_show_cleared_at' => now()->subMonths(1),
            'no_show_clear_reason' => 'Emergency hospitalization documented',
        ])->save();

        $badge = $this->trustService->badge($customer);
        $this->assertSame(TrustService::BADGE_NEW, $badge);
    }

    public function test_old_no_show_over_12_months_ago_does_not_result_in_flagged_badge(): void
    {
        $customer = Customer::factory()->create();

        Reservation::factory()->create([
            'customer_id' => $customer->customer_id,
            'slot_id' => $this->slot->slot_id,
            'booking_date' => now()->subMonths(14)->toDateString(),
        ])->forceFill([
            'status' => ReservationStatus::NoShow,
            'no_show_at' => now()->subMonths(14),
            'no_show_cleared_at' => null,
        ])->save();

        $badge = $this->trustService->badge($customer);
        $this->assertSame(TrustService::BADGE_NEW, $badge);
    }

    public function test_trust_profile_returns_accurate_metrics_and_history(): void
    {
        $customer = Customer::factory()->create([
            'full_name' => 'Alice Walker',
            'phone' => '0499887766',
        ]);

        // 1 completed visit
        Reservation::factory()->create([
            'customer_id' => $customer->customer_id,
            'slot_id' => $this->slot->slot_id,
            'booking_date' => '2026-08-01',
        ])->forceFill([
            'status' => ReservationStatus::Completed,
            'completed_at' => '2026-08-01 20:00:00',
        ])->save();

        // 1 late cancellation
        Reservation::factory()->create([
            'customer_id' => $customer->customer_id,
            'slot_id' => $this->slot->slot_id,
            'booking_date' => '2026-08-15',
        ])->forceFill([
            'status' => ReservationStatus::Cancelled,
            'cancelled_by' => 'customer',
            'is_late_cancellation' => true,
        ])->save();

        $profile = $this->trustService->profile($customer);

        $this->assertSame($customer->customer_id, $profile['customer_id']);
        $this->assertSame('Alice Walker', $profile['customer_name']);
        $this->assertSame('New', $profile['badge']);
        $this->assertSame(1, $profile['completed_visits_count']);
        $this->assertSame(1, $profile['cancellations_count']);
        $this->assertSame(1, $profile['late_cancellations_count']);
        $this->assertSame(0, $profile['uncleared_no_shows_count']);
        $this->assertCount(2, $profile['history']);
    }
}
