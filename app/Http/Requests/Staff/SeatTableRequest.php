<?php

namespace App\Http\Requests\Staff;

use Illuminate\Foundation\Http\FormRequest;

/** Authorization is role:waitstaff on the route — no per-table condition. */
class SeatTableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'guest_count' => ['nullable', 'integer', 'min:1', 'max:20'],
        ];
    }
}
