<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\UpdateProfileRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        return view('customer.profile', ['customer' => $request->user('customer')]);
    }

    public function update(UpdateProfileRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $customer = $request->user('customer');
        $previousEmail = $customer->email;

        $customer->update([
            'full_name' => $data['full_name'],
            'email' => $data['email'],
            'phone' => $data['phone'],
        ]);

        if ($customer->email !== $previousEmail) {
            $customer->forceFill(['email_verified_at' => null])->save();
            $customer->sendEmailVerificationNotification();
        }

        if ($request->filled('password')) {
            $customer->update(['password_hash' => $data['password']]);
        }

        return back()->with('status', 'profile-updated');
    }
}
