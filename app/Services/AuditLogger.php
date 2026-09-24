<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\HistoricalDataManagement;
use App\Models\Staff;
use Illuminate\Database\Eloquent\Model;

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

    /**
     * @param  array{tokens_in:int, tokens_out:int}  $usage
     */
    public function logAi(?Customer $actor, string $feature, array $usage, ?string $ipAddress = null): AuditLog
    {
        return AuditLog::create([
            'customer_id' => $actor?->customer_id,
            'action_type' => 'ai_request',
            'entity_name' => 'ai_request',
            'details' => ['feature' => $feature] + $usage,
            'ip_address' => $ipAddress,
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
