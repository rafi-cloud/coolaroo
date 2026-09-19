<?php

namespace App\Http\Requests\Staff\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class LoginStaffRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    public function authenticate(): void
    {
        $credentials = $this->only('email', 'password') + ['is_active' => true];

        if (! Auth::guard('staff')->attempt($credentials)) {
            throw ValidationException::withMessages([
                'email' => "That email and password don't match, or this account has been deactivated.",
            ]);
        }
    }
}
