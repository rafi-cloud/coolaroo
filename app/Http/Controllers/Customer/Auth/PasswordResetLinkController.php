<?php

namespace App\Http\Controllers\Customer\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\Auth\ForgotPasswordCustomerRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

class PasswordResetLinkController extends Controller
{
    public function create(): View
    {
        return view('customer.auth.forgot-password');
    }

    public function store(ForgotPasswordCustomerRequest $request): RedirectResponse
    {
        Password::sendResetLink($request->only('email'));

        return back()->with('status', "If that email matches an account, a reset link is on its way.");
    }
}
