<?php

namespace App\Http\Requests\Admin;

use App\Enums\RefundMethod;
use App\Models\Order;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * FR52, BR13. return_to_stock is unticked by default, so an absent checkbox
 * is a real "no" rather than a missing field.
 */
class ApproveRefundRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('issueRefund', Order::class);
    }

    public function rules(): array
    {
        return [
            'method' => ['required', Rule::enum(RefundMethod::class)],
            'return_to_stock' => ['nullable', 'boolean'],
            'manual_reference' => ['nullable', 'string', 'max:100', 'required_if:method,manual'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['return_to_stock' => $this->boolean('return_to_stock')]);
    }
}
