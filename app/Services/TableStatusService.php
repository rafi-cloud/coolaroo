<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\TableStatus;
use App\Enums\VisitCloseReason;
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
                'table' => "Table {$table->table_number} is {$table->status->value} — only an available table can be seated as a walk-in.",
            ]);
        }

        $this->transition($table, TableStatus::Occupied, $actor);

        return $table->visits()->create([
            'opened_by_staff_id' => $actor->staff_id,
            'guest_count' => $guestCount,
            'opened_at' => now(),
        ]);
    }

    /** 6.3: close_reason is a fixed vocabulary — staff_clear here, auto_clear for the idle sweep. */
    /**
     * BR01: a table is taken from the moment an order is placed on it, paid or
     * not. Both the customer checkout and the payment path call this, so an
     * order created before this rule still occupies its table when it settles;
     * it is idempotent either way.
     */
    public function occupyForOrder(RestaurantTable $table, ?Staff $actor = null): Visit
    {
        $visit = $table->visits()->whereNull('closed_at')->latest('visit_id')->first();

        if ($visit === null) {
            $visit = $table->visits()->create(['opened_at' => now(), 'opened_by_staff_id' => $actor?->staff_id]);
        } elseif ($visit->opened_at === null) {
            $visit->update([
                'opened_at' => now(),
                'opened_by_staff_id' => $actor?->staff_id ?? $visit->opened_by_staff_id,
            ]);
        }

        if ($table->status !== TableStatus::Occupied) {
            $this->transition($table, TableStatus::Occupied, $actor);
        }

        return $visit;
    }

    public function clearTable(
        RestaurantTable $table,
        ?Staff $actor = null,
        bool $force = false,
        VisitCloseReason $reason = VisitCloseReason::StaffClear,
    ): void {
        // Without this the stale drawer of a table someone else already cleared
        // reaches transition() and answers with a 409 page instead of a message.
        if ($table->status === TableStatus::Available) {
            throw ValidationException::withMessages([
                'table' => "Table {$table->table_number} is already available.",
            ]);
        }

        if (! $force && $this->hasActiveOrders($table)) {
            throw ValidationException::withMessages([
                'table' => "Table {$table->table_number} has active orders — confirm before clearing.",
            ]);
        }

        $openVisit = $table->visits()->whereNull('closed_at')->latest('opened_at')->first();

        if ($openVisit !== null) {
            $openVisit->update([
                'closed_at' => now(),
                'closed_by_staff_id' => $actor?->staff_id,
                'close_reason' => $reason,
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

            $this->clearTable($table, actor: null, force: true, reason: VisitCloseReason::AutoClear);
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
        // A table seated a minute ago has no orders yet, so the order checks
        // alone would sweep a party the moment they sat down. "Idle" has to
        // mean the table has been Occupied that long.
        if ($table->status_changed_at !== null && $table->status_changed_at->gt(now()->subMinutes($idleMinutes))) {
            return true;
        }

        if ($this->hasActiveOrders($table)) {
            return true;
        }

        return $table->orders()
            ->whereNotNull('paid_at')
            ->where('paid_at', '>=', now()->subMinutes($idleMinutes))
            ->exists();
    }
}
