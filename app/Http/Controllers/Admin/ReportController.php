<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ReportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * FR82–FR87, UC35, S41. Admin report pages for sales, items, operations, reservations, feedback, and staff.
 */
class ReportController extends Controller
{
    public const VALID_TYPES = [
        'sales' => 'Sales Report',
        'items' => 'Item & Category Report',
        'operations' => 'Operations Report',
        'reservations' => 'Reservation Report',
        'feedback' => 'Feedback Report',
        'staff' => 'Staff Activity Report',
    ];

    public function index(): RedirectResponse
    {
        return redirect()->route('admin.reports.show', ['type' => 'sales']);
    }

    public function show(string $type, Request $request, ReportService $reportService): View
    {
        if (! array_key_exists($type, self::VALID_TYPES)) {
            abort(404, 'Unknown report type');
        }

        $from = $request->filled('from')
            ? Carbon::parse($request->query('from'))->startOfDay()
            : today()->subDays(29)->startOfDay();

        $to = $request->filled('to')
            ? Carbon::parse($request->query('to'))->endOfDay()
            : today()->endOfDay();

        if ($from->isAfter($to)) {
            $from = (clone $to)->subDays(29)->startOfDay();
        }

        $data = match ($type) {
            'sales' => $reportService->salesReport($from, $to),
            'items' => $reportService->itemsReport($from, $to),
            'operations' => $reportService->operationsReport($from, $to),
            'reservations' => $reportService->reservationsReport($from, $to),
            'feedback' => $reportService->feedbackReport($from, $to),
            'staff' => $reportService->staffActivityReport($from, $to),
        };

        return view('admin.reports.show', [
            'type' => $type,
            'typeName' => self::VALID_TYPES[$type],
            'types' => self::VALID_TYPES,
            'data' => $data,
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
        ]);
    }
}
