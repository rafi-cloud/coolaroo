<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Screen S42 Archive Tab: Historical Data Management Snapshots.
 * FR99: View archived records (snapshots, BR62).
 */
class ArchiveController extends Controller
{
    public function __construct(
        private readonly AuditLogService $auditService,
    ) {}

    /**
     * S42 Archive Tab, UC36: Browse immutable entity snapshots with formatted JSON views.
     */
    public function index(Request $request): View
    {
        $filters = [
            'entity_name' => (string) $request->input('entity_name', ''),
            'search' => trim((string) $request->input('search', '')),
        ];

        $archives = $this->auditService->searchArchives($filters);
        $entities = $this->auditService->distinctArchiveEntities();

        return view('admin.audit-log.archive', [
            'archives' => $archives,
            'entities' => $entities,
            'filters' => $filters,
        ]);
    }
}
