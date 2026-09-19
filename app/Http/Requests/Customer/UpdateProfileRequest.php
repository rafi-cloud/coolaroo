<?php

namespace App\Http\Requests\Customer;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $customerId = $this->user('customer')->customer_id;

        return [
            'full_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'string', 'email', 'max:150', Rule::unique('customer', 'email')->ignore($customerId, 'customer_id')],
            'phone' => ['nullable', 'string', 'max:20', Rule::unique('customer', 'phone')->ignore($customerId, 'customer_id')],
            'current_password' => ['required_with:password', 'string', 'current_password:customer'],
            'password' => ['nullable', 'confirmed', Password::defaults()],
        ];
    }
}
