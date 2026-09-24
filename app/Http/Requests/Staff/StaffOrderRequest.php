<?php

namespace App\Http\Requests\Staff;

use Illuminate\Foundation\Http\FormRequest;

/**
 * item_size flattens item_id/size_id into one dropdown value
 * ("item:size") so the order-builder form needs no cascading-select JS —
 * CheckoutService::revalidate() is still the real source of truth on both.
 */
class StaffOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.item_size' => ['required', 'string', 'regex:/^\d+:\d+$/'],
            'lines.*.quantity' => ['required', 'integer', 'min:1', 'max:20'],
            'lines.*.special_request' => ['nullable', 'string', 'max:200'],
        ];
    }
}
