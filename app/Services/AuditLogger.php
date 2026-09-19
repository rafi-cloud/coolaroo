<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\HistoricalDataManagement;
use App\Models\Staff;
use Illuminate\Database\Eloquent\Model;

/**
 * FR89, BR62, NFR14.
 */
class AuditLogger
{
    public function log(Staff|Customer|null $actor, string $actionType, Model $entity, ?string $reason = null): AuditLog
    {
        return AuditLog::create([
            'staff_id' => $actor instanceof Staff ? $actor->staff_id : null,
            'customer_id' => $actor instanceof Customer ? $actor->customer_id : null,
            'action_type' => $actionType,
            'entity_name' => $entity->getTable(),
            'entity_id' => $entity->getKey(),
            'details' => array_filter(['reason' => $reason]),
        ]);
    }

    public function snapshot(Staff|Customer|null $actor, string $actionType, Model $entity, ?string $reason = null): AuditLog
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
