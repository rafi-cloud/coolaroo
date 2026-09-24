<?php

namespace App\Http\Controllers\Customer\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\Auth\LoginCustomerRequest;
use App\Models\RestaurantTable;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedCustomerController extends Controller
{
    public function __construct(private AuditLogger $auditLogger) {}

    public function create(Request $request): View
    {
        $tableId = $request->session()->get('qr.table_id');

        return view('customer.auth.login', [
            'qrTable' => $tableId !== null ? RestaurantTable::find($tableId) : null,
        ]);
    }

    public function store(LoginCustomerRequest $request): RedirectResponse
    {
        $request->authenticate();

        $customer = $request->user('customer');

        if (! $customer->hasVerifiedEmail()) {
            Auth::guard('customer')->logout();

            return redirect()->route('verification.notice')
                ->with('verification.email', $customer->email);
        }

        $request->session()->regenerate();
        $customer->update(['last_login_at' => now()]);

        $this->auditLogger->log($customer, 'login', $customer);

        return redirect()->intended('/');
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('customer')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
