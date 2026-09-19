<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureStaffSessionIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $staff = $request->user('staff');

        if ($staff === null) {
            return $next($request);
        }

        $timeoutMinutes = (int) (Setting::find('staff_session_timeout_minutes')?->setting_value ?? 30);
        $lastActivity = $request->session()->get('staff_last_activity');

        if ($lastActivity !== null && now()->diffInMinutes($lastActivity, absolute: true) >= $timeoutMinutes) {
            Auth::guard('staff')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('staff.login')
                ->with('status', "You were signed out after {$timeoutMinutes} minutes of inactivity.");
        }

        $request->session()->put('staff_last_activity', now());

        return $next($request);
    }
}
