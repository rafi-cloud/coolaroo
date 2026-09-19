<?php

namespace App\Http\Controllers\Customer\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\Auth\RegisterCustomerRequest;
use App\Models\Customer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class RegisteredCustomerController extends Controller
{
    public function create(): View
    {
        return view('customer.auth.register');
    }

    public function store(RegisterCustomerRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $customer = Customer::create([
            'full_name' => $data['full_name'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'password_hash' => $data['password'],
        ]);

        $customer->sendEmailVerificationNotification();

        Auth::guard('customer')->login($customer);

        $request->session()->regenerate();

        return redirect()->intended('/');
    }
}
