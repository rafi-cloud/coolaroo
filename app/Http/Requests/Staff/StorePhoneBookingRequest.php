<?php

namespace App\Http\Requests\Staff;

use Illuminate\Foundation\Http\FormRequest;

/**
 * FR64: Phone booking request validation.
 */
class StorePhoneBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'customer_id' => ['nullable', 'integer', 'exists:customer,customer_id'],
            'guest_name' => ['required_without:customer_id', 'nullable', 'string', 'max:100'],
            'guest_phone' => ['required_without:customer_id', 'nullable', 'string', 'max:20'],
            'booking_date' => ['required', 'date', 'after_or_equal:today'],
            'slot_id' => ['required_without:booking_time', 'nullable', 'integer', 'exists:slot_capacity,slot_id'],
            'booking_time' => ['required_without:slot_id', 'nullable', 'string'],
            'party_size' => ['required', 'integer', 'min:1', 'max:50'],
            'special_requests' => ['nullable', 'string', 'max:500'],
        ];
    }
}
