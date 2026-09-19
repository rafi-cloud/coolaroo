<?php

namespace App\Services;

use App\Enums\TableStatus;
use App\Models\RestaurantTable;
use App\Models\Staff;
use Illuminate\Support\Str;

/**
 * FR11, FR12, FR13, FR20, BR62. The real transition engine
 * (TableStatusService) is T053 — overrideStatus() here is deliberately the
 * one place that bypasses it, per FR20's "any status, mandatory reason."
 */
class TableService
{
    public function __construct(private AuditLogger $auditLogger)
    {
    }

    public function create(array $data): RestaurantTable
    {
        return RestaurantTable::create($data + ['qr_token' => Str::random(64)]);
    }

    public function update(RestaurantTable $table, array $data): ?string
    {
        $warning = $this->seatWarning($table, $data['seat_capacity']);

        $table->update($data);

        return $warning;
    }

    public function deactivate(RestaurantTable $table, Staff $actor, ?string $reason = null): void
    {
        $table->update(['is_active' => false]);

        $this->auditLogger->snapshot($actor, 'table_deactivate', $table, $reason);
    }

    public function reactivate(RestaurantTable $table, Staff $actor): void
    {
        $table->update(['is_active' => true]);

        $this->auditLogger->log($actor, 'table_reactivate', $table);
    }

    public function overrideStatus(RestaurantTable $table, TableStatus $status, Staff $actor, string $reason): void
    {
        $table->forceFill([
            'status' => $status,
            'status_changed_at' => now(),
        ])->save();

        $this->auditLogger->log($actor, 'table_status', $table, $reason);
    }

    private function seatWarning(RestaurantTable $table, int $newSeatCapacity): ?string
    {
        $tooSmall = $table->visits()
            ->whereNull('opened_at')
            ->whereNull('closed_at')
            ->whereHas('reservation', function ($query) use ($newSeatCapacity) {
                $query->whereDate('booking_date', '>=', now()->toDateString())
                    ->where('party_size', '>', $newSeatCapacity);
            })
            ->exists();

        return $tooSmall
            ? 'This table has a future reservation with more guests than the new seat capacity allows.'
            : null;
    }
}
