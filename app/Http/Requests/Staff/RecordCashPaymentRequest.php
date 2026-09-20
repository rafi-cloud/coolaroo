<?php

namespace App\Http\Requests\Staff;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class RecordCashPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'amount_received' => ['required', 'numeric', 'min:0'],
            'adjustment_amount' => ['nullable', 'numeric', 'min:0'],
            'adjustment_category' => ['nullable', Rule::in(['complaint', 'staff_meal', 'manager_comp', 'other'])],
            'adjustment_note' => ['nullable', 'string', 'max:255'],
        ];
    }

    /** BR23: category and note become mandatory only once an adjustment is actually given. */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ((float) ($this->input('adjustment_amount') ?? 0) <= 0) {
                return;
            }

            if (blank($this->input('adjustment_category'))) {
                $validator->errors()->add('adjustment_category', 'A category is required when giving an adjustment.');
            }

            if (blank($this->input('adjustment_note'))) {
                $validator->errors()->add('adjustment_note', 'A note is required when giving an adjustment.');
            }
        });
    }
}
