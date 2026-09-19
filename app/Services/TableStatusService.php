<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\TableStatus;
use App\Events\TableStatusChanged;
use App\Models\RestaurantTable;
use App\Models\Staff;
use App\Models\Visit;
use Illuminate\Validation\ValidationException;

/**
 * FR19, FR21, BR01-BR07. The real guarded transition engine — T050's
 * overrideStatus() deliberately bypasses this, for FR20's unguarded case.
 */
class TableStatusService
{
    private const ACTIVE_ORDER_STATUSES = [
        OrderStatus::PendingPayment,
        OrderStatus::Paid,
        OrderStatus::Preparing,
        OrderStatus::Ready,
    ];

    public function __construct(private AuditLogger $auditLogger)
    {
    }

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

    public function seatWalkIn(RestaurantTable $table, Staff $actor, ?int $guestCount = null): Visit
    {
        $this->transition($table, TableStatus::Occupied, $actor);

        return $table->visits()->create([
            'opened_by_staff_id' => $actor->staff_id,
            'guest_count' => $guestCount,
            'opened_at' => now(),
        ]);
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
