<?php

namespace Tests\Feature\Services;

use App\Enums\TableStatus;
use App\Exceptions\InvalidTransitionException;
use App\Models\AuditLog;
use App\Models\Order;
use App\Models\RestaurantTable;
use App\Models\Staff;
use App\Services\TableStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TableStatusServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_legal_transition_updates_status_and_writes_an_audit_log(): void
    {
        $table = RestaurantTable::factory()->create(['status' => TableStatus::Available]);

        app(TableStatusService::class)->transition($table, TableStatus::Occupied);

        $this->assertSame(TableStatus::Occupied, $table->fresh()->status);
        $this->assertNotNull(AuditLog::where('entity_name', 'restaurant_table')->where('action_type', 'table_status')->first());
    }

    public function test_an_illegal_transition_is_rejected(): void
    {
        $table = RestaurantTable::factory()->create(['status' => TableStatus::Available]);

        $this->expectException(InvalidTransitionException::class);

        // Available -> Available isn't in TableStatus::transitions() for Available.
        app(TableStatusService::class)->transition($table, TableStatus::Available);
    }

    public function test_an_inactive_table_cannot_change_status(): void
    {
        $table = RestaurantTable::factory()->create(['status' => TableStatus::Available, 'is_active' => false]);

        $this->expectException(ValidationException::class);

        app(TableStatusService::class)->transition($table, TableStatus::Occupied);
    }

    public function test_seating_a_walk_in_opens_a_visit_and_occupies_the_table(): void
    {
        $table = RestaurantTable::factory()->create(['status' => TableStatus::Available]);
        $staff = Staff::factory()->create();

        $visit = app(TableStatusService::class)->seatWalkIn($table, $staff, guestCount: 4);

        $this->assertSame(TableStatus::Occupied, $table->fresh()->status);
        $this->assertNull($visit->closed_at);
        $this->assertSame(4, $visit->guest_count);
    }

    public function test_clearing_a_table_with_active_orders_requires_force(): void
    {
        $table = RestaurantTable::factory()->create(['status' => TableStatus::Occupied]);
        $this->makeOrder($table, 'paid');

        $this->expectException(ValidationException::class);

        app(TableStatusService::class)->clearTable($table);
    }

    /** UC17's own alternate: a walk-in never takes a Reserved table. */
    public function test_a_reserved_table_cannot_be_seated_as_a_walk_in(): void
    {
        $table = RestaurantTable::factory()->create(['status' => TableStatus::Reserved]);

        $this->expectException(ValidationException::class);

        app(TableStatusService::class)->seatWalkIn($table, Staff::factory()->create());
    }

    /** BR06: one table with an active order blocks the whole group. */
    public function test_clearing_a_group_is_blocked_if_any_table_has_active_orders(): void
    {
        $clean = RestaurantTable::factory()->create(['status' => TableStatus::Occupied]);
        $busy = RestaurantTable::factory()->create(['status' => TableStatus::Occupied]);
        $this->makeOrder($busy, 'paid');

        $this->expectException(ValidationException::class);

        try {
            app(TableStatusService::class)->clearGroup(collect([$clean, $busy]), Staff::factory()->create());
        } finally {
            $this->assertSame(TableStatus::Occupied, $clean->fresh()->status);
        }
    }

    /** BR05: a party seated a minute ago has no orders yet and must not be swept. */
    public function test_auto_clear_leaves_a_table_that_was_only_just_occupied(): void
    {
        $justSeated = RestaurantTable::factory()->create(['status' => TableStatus::Occupied]);
        $justSeated->forceFill(['status_changed_at' => now()->subMinutes(2)])->save();

        $cleared = app(TableStatusService::class)->autoClearIdleTables(idleMinutes: 45);

        $this->assertSame(0, $cleared);
        $this->assertSame(TableStatus::Occupied, $justSeated->fresh()->status);
    }

    public function test_auto_clear_skips_a_table_paid_within_the_idle_window_but_clears_an_old_one(): void
    {
        $recentlyPaid = RestaurantTable::factory()->create(['status' => TableStatus::Occupied]);
        $recentlyPaid->forceFill(['status_changed_at' => now()->subMinutes(90)])->save();
        $this->makeOrder($recentlyPaid, 'served', now()->subMinutes(10));

        $longDone = RestaurantTable::factory()->create(['status' => TableStatus::Occupied]);
        $longDone->forceFill(['status_changed_at' => now()->subMinutes(90)])->save();
        $this->makeOrder($longDone, 'served', now()->subMinutes(60));

        $cleared = app(TableStatusService::class)->autoClearIdleTables(idleMinutes: 45);

        $this->assertSame(1, $cleared);
        $this->assertSame(TableStatus::Occupied, $recentlyPaid->fresh()->status);
        $this->assertSame(TableStatus::Available, $longDone->fresh()->status);
    }

    private function makeOrder(RestaurantTable $table, string $status, ?Carbon $paidAt = null): Order
    {
        $order = new Order;
        $order->forceFill([
            'table_id' => $table->table_id,
            'order_number' => 'ORD'.fake()->unique()->numberBetween(1000, 9999),
            'idempotency_key' => (string) Str::uuid(),
            'status' => $status,
            'total_amount' => 25,
            'gst_amount' => 2.27,
            'paid_at' => $paidAt,
        ])->save();

        return $order;
    }
}
