<?php

namespace App\Http\Controllers\Staff\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\Auth\LoginStaffRequest;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\View\View;

class AuthenticatedStaffController extends Controller
{
    public function __construct(private AuditLogger $auditLogger) {}

    public function create(): View
    {
        return view('staff.auth.login');
    }

    public function store(LoginStaffRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        $staff = $request->user('staff');
        $staff->update(['last_login_at' => now()]);
        $request->session()->put('staff_last_activity', now());

        $this->auditLogger->log($staff, 'login', $staff);

        $landingScreen = $staff->role->landing_screen;

        return redirect()->to(Route::has($landingScreen) ? route($landingScreen) : '/');
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('staff')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('staff.login');
    }
}
