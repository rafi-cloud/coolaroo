<?php

namespace App\Http\Requests\Staff;

use Illuminate\Foundation\Http\FormRequest;

/**
 * FR59: a signed delta in minutes. Authorization stays in the controller —
 * the ability needs the destination from the route, not just the request.
 */
class AdjustEtaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'minutes' => ['required', 'integer', 'not_in:0'],
        ];
    }
}
