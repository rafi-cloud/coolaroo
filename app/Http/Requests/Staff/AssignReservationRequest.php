<?php

namespace App\Http\Requests\Staff;

use App\Enums\ReservationStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignReservationRequest extends FormRequest
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
                Rule::exists('reservation', 'reservation_id')->whereIn('status', [
                    ReservationStatus::Requested->value,
                    ReservationStatus::Confirmed->value,
                ]),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'reservation_id.exists' => 'That booking can no longer be given a table.',
        ];
    }
}
