<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ReportService;
use Illuminate\Http\Request;
use Illuminate\View\View;

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
