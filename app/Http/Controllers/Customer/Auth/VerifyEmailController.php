<?php

namespace App\Http\Controllers\Customer\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;

class VerifyEmailController extends Controller
{
    public function __invoke(EmailVerificationRequest $request): RedirectResponse
    {
        if ($request->user('customer')->hasVerifiedEmail()) {
            return redirect()->intended('/');
        }

        if ($request->user('customer')->markEmailAsVerified()) {
            event(new Verified($request->user('customer')));
        }

        return redirect()->intended('/')->with('status', 'verified');
    }
}
