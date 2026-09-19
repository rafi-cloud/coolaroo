<?php

namespace App\Http\Controllers\Customer\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\Auth\LoginCustomerRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedCustomerController extends Controller
{
    public function create(): View
    {
        return view('customer.auth.login');
    }

    public function store(LoginCustomerRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        $request->user('customer')->update(['last_login_at' => now()]);

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
