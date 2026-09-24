<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\HistoricalDataManagement;
use App\Models\Staff;
use Carbon\Carbon;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class AuditLogService
{
    private function parseDate(mixed $value): ?Carbon
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return null;
        }

        try {
            return Carbon::parse((string) $value);
        } catch (InvalidFormatException) {
            return null;
        }
    }

    /**
     * @param  array{action_type?: string, entity_name?: string, staff_id?: int|string, from?: string, to?: string, search?: string}  $filters
     */
    public function searchLogs(array $filters = []): LengthAwarePaginator
    {
        $query = AuditLog::with(['staff.role', 'customer', 'archive'])
            ->orderByDesc('log_id');

        if (! empty($filters['action_type'])) {
            $query->where('action_type', $filters['action_type']);
        }

        if (! empty($filters['entity_name'])) {
            $query->where('entity_name', $filters['entity_name']);
        }

        if (! empty($filters['staff_id'])) {
            $query->where('staff_id', (int) $filters['staff_id']);
        }

        if (! empty($filters['from']) && ($from = $this->parseDate($filters['from'])) !== null) {
            $query->where('logged_at', '>=', $from->startOfDay());
        }

        if (! empty($filters['to']) && ($to = $this->parseDate($filters['to'])) !== null) {
            $query->where('logged_at', '<=', $to->endOfDay());
        }

        if (! empty($filters['search'])) {
            $search = trim((string) $filters['search']);
            $query->where(function ($q) use ($search) {
                $q->where('ip_address', 'like', "%{$search}%")
                    ->orWhere('entity_id', $search)
                    ->orWhere('details', 'like', "%{$search}%")
                    ->orWhereHas('staff', function ($sq) use ($search) {
                        $sq->where('full_name', 'like', "%{$search}%");
                    })
                    ->orWhereHas('customer', function ($cq) use ($search) {
                        $cq->where('full_name', 'like', "%{$search}%");
                    });
            });
        }

        return $query->paginate(25)->withQueryString();
    }

    /**
     * @param  array{entity_name?: string, search?: string}  $filters
     */
    public function searchArchives(array $filters = []): LengthAwarePaginator
    {
        $query = HistoricalDataManagement::with(['auditLog.staff.role'])
            ->orderByDesc('history_id');

        if (! empty($filters['entity_name'])) {
            $query->where('entity_name', $filters['entity_name']);
        }

        if (! empty($filters['search'])) {
            $search = trim((string) $filters['search']);
            $query->where(function ($q) use ($search) {
                $q->where('record_id', 'like', "%{$search}%")
                    ->orWhere('record_data', 'like', "%{$search}%");
            });
        }

        return $query->paginate(20)->withQueryString();
    }

    /**
     * @return list<string>
     */
    public function distinctActionTypes(): array
    {
        return AuditLog::query()
            ->distinct()
            ->pluck('action_type')
            ->sort()
            ->values()
            ->toArray();
    }

    /**
     * @return list<string>
     */
    public function distinctEntityNames(): array
    {
        return AuditLog::query()
            ->distinct()
            ->pluck('entity_name')
            ->sort()
            ->values()
            ->toArray();
    }

    /**
     * @return list<string>
     */
    public function distinctArchiveEntities(): array
    {
        return HistoricalDataManagement::query()
            ->distinct()
            ->pluck('entity_name')
            ->sort()
            ->values()
            ->toArray();
    }

    public function staffList(): Collection
    {
        return Staff::query()->orderBy('full_name')->get(['staff_id', 'full_name']);
    }
}
