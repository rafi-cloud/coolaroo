<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreStaffRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'string', 'email', 'max:150', Rule::unique('staff', 'email')],
            'phone' => ['nullable', 'string', 'max:20'],
            'role_id' => ['required', Rule::exists('role', 'role_id')],
            'password' => ['required', 'confirmed', Password::defaults()],
        ];
    }
}
