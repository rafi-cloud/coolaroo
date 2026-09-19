<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStaffRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $staffId = $this->route('staff')->staff_id;

        return [
            'full_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'string', 'email', 'max:150', Rule::unique('staff', 'email')->ignore($staffId, 'staff_id')],
            'phone' => ['nullable', 'string', 'max:20'],
            'role_id' => ['required', Rule::exists('role', 'role_id')],
        ];
    }
}
