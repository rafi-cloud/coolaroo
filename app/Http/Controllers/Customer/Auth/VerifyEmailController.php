<?php

namespace App\Http\Controllers\Customer\Auth;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class VerifyEmailController extends Controller
{
    public function __invoke(Request $request, string $id, string $hash): RedirectResponse
    {
        $customer = Customer::findOrFail($id);

        if (! hash_equals($hash, sha1($customer->getEmailForVerification()))) {
            abort(403);
        }

        if ($customer->hasVerifiedEmail()) {
            return redirect()->route('customer.login')->with('status', 'already-verified');
        }

        $customer->markEmailAsVerified();

        event(new Verified($customer));

        Auth::guard('customer')->login($customer);

        $request->session()->regenerate();

        return redirect('/')->with('status', 'verified');
    }
}
