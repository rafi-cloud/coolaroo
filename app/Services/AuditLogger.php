<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\HistoricalDataManagement;
use App\Models\Staff;
use Illuminate\Database\Eloquent\Model;

/**
 * NFR14, BR62. Minimal on purpose — see T024's guide for why this exists
 * ahead of T151, which owns extending it (more action types, ip_address, …).
 */
class AuditLogger
{
    public function log(?Staff $actor, string $actionType, Model $entity, ?string $reason = null): AuditLog
    {
        return AuditLog::create([
            'staff_id' => $actor?->staff_id,
            'action_type' => $actionType,
            'entity_name' => $entity->getTable(),
            'entity_id' => $entity->getKey(),
            'details' => array_filter(['reason' => $reason]),
        ]);
    }

    public function snapshot(?Staff $actor, string $actionType, Model $entity, ?string $reason = null): AuditLog
    {
        $log = $this->log($actor, $actionType, $entity, $reason);

        HistoricalDataManagement::create([
            'log_id' => $log->log_id,
            'entity_name' => $entity->getTable(),
            'record_id' => (string) $entity->getKey(),
            'record_data' => $entity->toArray(),
        ]);

        return $log;
    }
}
