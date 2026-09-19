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
        return $request->user('customer')->hasVerifiedEmail()
            ? redirect()->intended('/')
            : view('customer.auth.verify-email');
    }
}
