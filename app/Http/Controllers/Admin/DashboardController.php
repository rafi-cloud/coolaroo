<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ReportService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * FR81, 08.5, UC35. Admin live dashboard with 15 widgets.
 */
class DashboardController extends Controller
{
    public function index(Request $request, ReportService $reportService): View
    {
        $trendDays = in_array((int) $request->query('trend', 7), [7, 30], true) ? (int) $request->query('trend', 7) : 7;
        $dashboard = $reportService->dashboard($trendDays);
        $trend7 = $trendDays === 7 ? $dashboard['sales_trend'] : $reportService->salesTrend(7);
        $trend30 = $trendDays === 30 ? $dashboard['sales_trend'] : $reportService->salesTrend(30);

        return view('admin.dashboard', [
            'dashboard' => $dashboard,
            'trend7' => $trend7,
            'trend30' => $trend30,
            'currentTrendDays' => $trendDays,
        ]);
    }
}
