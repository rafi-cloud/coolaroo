<?php

namespace App\Http\Requests\Staff;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** FR94: UC41's two choices, exactly. */
class ResolveStockConflictRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'resolution' => ['required', Rule::in(['will_make_it', 'refund_request'])],
        ];
    }
}
