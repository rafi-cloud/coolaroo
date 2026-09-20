<?php

namespace App\Http\Requests\Staff;

use Illuminate\Foundation\Http\FormRequest;

class RequestRefundRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'order_item_id' => ['required', 'integer'],
            'quantity' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'max:255'],
        ];
    }
}
