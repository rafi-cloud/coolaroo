<?php

namespace App\Http\Requests\Staff;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Authorization is role:waitstaff on the route — no per-table condition. */
class SeatReservationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reservation_id' => [
                'required',
                'integer',
                Rule::exists('visit', 'reservation_id')
                    ->where('table_id', $this->route('table')->table_id)
                    ->whereNull('closed_at'),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'reservation_id.exists' => 'That booking is not assigned to this table.',
        ];
    }
}
