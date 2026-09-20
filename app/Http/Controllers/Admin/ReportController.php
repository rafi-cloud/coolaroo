<?php

namespace App\Http\Controllers\Admin;

use App\Events\SettingSwitched;
use App\Http\Controllers\Controller;
use App\Services\AuditLogger;
use App\Services\ReportService;
use App\Services\SettingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * FR45, FR82–FR88, FR98, UC35, S41. Admin report pages for sales, items, operations, reservations, feedback, staff, and AI usage.
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
        'ai' => 'AI Usage Report',
    ];

    public function index(): RedirectResponse
    {
        return redirect()->route('admin.reports.show', ['type' => 'sales']);
    }

    public function show(string $type, Request $request, ReportService $reportService, SettingService $settings): View
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
            'ai' => $reportService->aiReport($from, $to),
        };

        return view('admin.reports.show', [
            'type' => $type,
            'typeName' => self::VALID_TYPES[$type],
            'types' => self::VALID_TYPES,
            'data' => $data,
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'aiEnabled' => $settings->getBool('ai_enabled', true),
        ]);
    }

    /**
     * FR98, BR49. Toggle AI Assistant on/off switch.
     */
    public function toggleAi(Request $request, SettingService $settings, AuditLogger $audit): RedirectResponse
    {
        $current = $settings->getBool('ai_enabled', true);
        $new = ! $current;
        $setting = $settings->set('ai_enabled', $new ? '1' : '0', $request->user('staff'));

        $audit->log(
            $request->user('staff'),
            'setting_update',
            $setting,
            sprintf('AI Assistant %s by administrator', $new ? 'enabled' : 'disabled')
        );

        event(new SettingSwitched('ai_enabled', $new ? '1' : '0'));

        return back()->with('status', sprintf('AI Assistant successfully %s.', $new ? 'enabled' : 'disabled'));
    }

    /**
     * FR88, UC35. Export report as PDF or CSV.
     */
    public function export(string $type, Request $request, ReportService $reportService): Response
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

        $format = strtolower((string) $request->query('format', 'csv'));
        $filename = sprintf('report-%s-%s-to-%s', $type, $from->toDateString(), $to->toDateString());

        if ($format === 'pdf') {
            $pdfBytes = $reportService->exportPdf($type, $from, $to);

            return response($pdfBytes, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => sprintf('attachment; filename="%s.pdf"', $filename),
            ]);
        }

        $csvString = $reportService->exportCsv($type, $from, $to);

        return response($csvString, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => sprintf('attachment; filename="%s.csv"', $filename),
        ]);
    }
}
