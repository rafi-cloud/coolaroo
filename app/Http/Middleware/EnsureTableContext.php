<?php

namespace App\Http\Middleware;

use App\Models\RestaurantTable;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * BR57. FR35's own wording: outside table context, show
 * "Scan the QR code on your table to order," don't just 403.
 */
class EnsureTableContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $tableId = $request->session()->get('table_id');
        $table = $tableId !== null ? RestaurantTable::find($tableId) : null;

        if ($table === null || ! $table->is_active) {
            return back()->with('error', 'Scan the QR code on your table to order.');
        }

        return $next($request);
    }
}
