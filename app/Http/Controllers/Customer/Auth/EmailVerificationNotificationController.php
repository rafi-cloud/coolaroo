<?php

namespace App\Http\Controllers\Customer\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class EmailVerificationNotificationController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        if ($request->user('customer')->hasVerifiedEmail()) {
            return redirect()->intended('/');
        }

        $request->user('customer')->sendEmailVerificationNotification();

        return back()->with('status', 'verification-link-sent');
    }
}
