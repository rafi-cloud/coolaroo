<?php

namespace App\Http\Controllers\Customer\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EmailVerificationPromptController extends Controller
{
    public function __invoke(Request $request): View|RedirectResponse
    {
        if ($customer = $request->user('customer')) {
            return $customer->hasVerifiedEmail()
                ? redirect()->intended('/')
                : view('customer.auth.verify-email', ['email' => $customer->email]);
        }

        $email = $request->session()->get('verification.email');

        if ($email === null) {
            return redirect()->route('customer.login');
        }

        $request->session()->keep('verification.email');

        return view('customer.auth.verify-email', ['email' => $email]);
    }
}
