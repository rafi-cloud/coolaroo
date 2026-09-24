<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public function __construct(
        private readonly AuditLogService $auditService,
    ) {}

    private function scalar(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }

    public function index(Request $request): View
    {
        $filters = [
            'action_type' => $this->scalar($request->input('action_type')),
            'entity_name' => $this->scalar($request->input('entity_name')),
            'staff_id' => $this->scalar($request->input('staff_id')) ?: null,
            'from' => $this->scalar($request->input('from')),
            'to' => $this->scalar($request->input('to')),
            'search' => trim($this->scalar($request->input('search'))),
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
