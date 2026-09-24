<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\RestaurantTable;
use App\Services\WaiterCallService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Public on purpose — says this works from the
 * QR login page without login; the session check is what keeps it to people
 * who actually scanned this table ("from table ordering page or QR
 * login page").
 */
class WaiterCallController extends Controller
{
    public function __construct(private WaiterCallService $waiterCalls) {}

    public function store(Request $request, RestaurantTable $table): RedirectResponse|View
    {
        abort_unless($this->isAtTable($request, $table), 403);

        if (! $table->is_active) {
            return view('public.table-unavailable', ['table' => $table]);
        }

        return back()->with(
            'status',
            $this->waiterCalls->call($table) ? 'waiter-called' : 'waiter-cooldown',
        );
    }

    private function isAtTable(Request $request, RestaurantTable $table): bool
    {
        return in_array($table->table_id, [
            $request->session()->get('table_id'),
            $request->session()->get('qr.table_id'),
        ], true);
    }
}
