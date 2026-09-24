<?php

namespace App\Http\Controllers\Customer\Auth;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class EmailVerificationNotificationController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        if ($customer = $request->user('customer')) {
            return $customer->hasVerifiedEmail()
                ? redirect()->intended('/')
                : tap(back()->with('status', 'verification-link-sent'), fn () => $customer->sendEmailVerificationNotification());
        }

        $validated = $request->validate([
            'email' => ['required', 'string', 'email'],
        ]);

        $customer = Customer::where('email', $validated['email'])->first();

        if ($customer !== null && ! $customer->hasVerifiedEmail()) {
            $customer->sendEmailVerificationNotification();
        }

        return redirect()->route('verification.notice')
            ->with('verification.email', $validated['email'])
            ->with('status', 'verification-link-sent');
    }
}
