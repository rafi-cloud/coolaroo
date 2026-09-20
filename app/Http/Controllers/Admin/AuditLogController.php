<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Screen S42: Audit Log Search and Detail Inspection.
 * FR90: Search audit log (filter by user, action, entity, date; view before/after JSON).
 */
class AuditLogController extends Controller
{
    public function __construct(
        private readonly AuditLogService $auditService,
    ) {}

    /**
     * S42, UC36: Display paginated audit logs with search, actor/action filters, and JSON diffs.
     */
    public function index(Request $request): View
    {
        $filters = [
            'action_type' => (string) $request->input('action_type', ''),
            'entity_name' => (string) $request->input('entity_name', ''),
            'staff_id' => $request->input('staff_id'),
            'from' => (string) $request->input('from', ''),
            'to' => (string) $request->input('to', ''),
            'search' => trim((string) $request->input('search', '')),
        ];

        $logs = $this->auditService->searchLogs($filters);
        $actionTypes = $this->auditService->distinctActionTypes();
        $entityNames = $this->auditService->distinctEntityNames();
        $staffMembers = $this->auditService->staffList();

        return view('admin.audit-log.index', [
            'logs' => $logs,
            'actionTypes' => $actionTypes,
            'entityNames' => $entityNames,
            'staffMembers' => $staffMembers,
            'filters' => $filters,
        ]);
    }
}
