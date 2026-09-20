<?php

namespace App\Http\Requests\Admin;

use App\Models\Order;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/** FR52. rejection_reason is required when rejected (06.4.22). */
class RejectRefundRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('issueRefund', Order::class);
    }

    public function rules(): array
    {
        return [
            'rejection_reason' => ['required', 'string', 'max:255'],
        ];
    }
}
