<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\TableStatus;
use App\Events\TableStatusChanged;
use App\Models\RestaurantTable;
use App\Models\Staff;
use App\Models\Visit;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The real guarded transition engine — the
 * overrideStatus deliberately bypasses this, for the unguarded case.
 */
class TableStatusService
{
    private const ACTIVE_ORDER_STATUSES = [
        OrderStatus::PendingPayment,
        OrderStatus::Paid,
        OrderStatus::Preparing,
        OrderStatus::Ready,
    ];

    public function __construct(private AuditLogger $auditLogger) {}

    public function transition(RestaurantTable $table, TableStatus $to, ?Staff $actor = null): void
    {
        if (! $table->is_active) {
            throw ValidationException::withMessages([
                'table' => 'Inactive tables cannot change status.',
            ]);
        }

        $table->status->ensureCanTransitionTo($to);

        $table->forceFill(['status' => $to, 'status_changed_at' => now()])->save();

        $this->auditLogger->log($actor, 'table_status', $table);

        event(new TableStatusChanged($table));
    }

    /** "Reserved or inactive tables blocked" — a walk-in never displaces a reservation. */
    public function seatWalkIn(RestaurantTable $table, Staff $actor, ?int $guestCount = null): Visit
    {
        if ($table->status !== TableStatus::Available) {
            throw ValidationException::withMessages([
                'table' => 'Only an available table can be seated as a walk-in.',
            ]);
        }

        $this->transition($table, TableStatus::Occupied, $actor);

        return $table->visits()->create([
            'opened_by_staff_id' => $actor->staff_id,
            'guest_count' => $guestCount,
            'opened_at' => now(),
        ]);
    }

    /** "one or more tables" — each gets its own transition and visit row, one guest count. */
    public function seatGroup(Collection $tables, Staff $actor, ?int $guestCount = null): Collection
    {
        return DB::transaction(fn () => $tables->map(
            fn (RestaurantTable $table) => $this->seatWalkIn($table, $actor, $guestCount)
        ));
    }

    public function clearTable(RestaurantTable $table, ?Staff $actor = null, bool $force = false): void
    {
        if (! $force && $this->hasActiveOrders($table)) {
            throw ValidationException::withMessages([
                'table' => 'This table has active orders — confirm before clearing.',
            ]);
        }

        $openVisit = $table->visits()->whereNull('closed_at')->latest('opened_at')->first();

        if ($openVisit !== null) {
            $openVisit->update([
                'closed_at' => now(),
                'closed_by_staff_id' => $actor?->staff_id,
                'close_reason' => 'cleared',
            ]);
        }

        $this->transition($table, TableStatus::Available, $actor);
    }

    /** any table in the group needing confirmation blocks the whole group. */
    public function clearGroup(Collection $tables, Staff $actor, bool $force = false): void
    {
        if (! $force && $tables->contains(fn (RestaurantTable $table) => $this->hasActiveOrders($table))) {
            throw ValidationException::withMessages([
                'table' => 'One or more tables in this group has active orders — confirm before clearing.',
            ]);
        }

        DB::transaction(function () use ($tables, $actor, $force) {
            $tables->each(fn (RestaurantTable $table) => $this->clearTable($table, $actor, $force));
        });
    }

    public function autoClearIdleTables(int $idleMinutes = 45): int
    {
        $cleared = 0;

        foreach (RestaurantTable::where('status', TableStatus::Occupied)->get() as $table) {
            if ($this->hasBlockingOrdersForAutoClear($table, $idleMinutes)) {
                continue;
            }

            $this->clearTable($table, actor: null, force: true);
            $cleared++;
        }

        return $cleared;
    }

    private function hasActiveOrders(RestaurantTable $table): bool
    {
        return $table->orders()->whereIn('status', self::ACTIVE_ORDER_STATUSES)->exists();
    }

    private function hasBlockingOrdersForAutoClear(RestaurantTable $table, int $idleMinutes): bool
    {
        if ($this->hasActiveOrders($table)) {
            return true;
        }

        return $table->orders()
            ->whereNotNull('paid_at')
            ->where('paid_at', '>=', now()->subMinutes($idleMinutes))
            ->exists();
    }
}
