<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * qr_ordering_enabled = 0 blocks customer cart/checkout (staff orders
 * are unaffected — this middleware never runs on a staff route).
 */
class EnsureQrOrderingEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        $enabled = Setting::find('qr_ordering_enabled')?->setting_value ?? '1';

        if ($enabled === '0') {
            return back()->with('error', 'Online ordering is paused right now — please order with a staff member.');
        }

        return $next($request);
    }
}
