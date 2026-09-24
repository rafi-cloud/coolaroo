<?php

namespace App\Http\Requests\Public;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AiChatRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'message' => ['required', 'string', 'max:500'],
            'history' => ['sometimes', 'array', 'max:10'],
            'history.*.role' => ['required', 'string', Rule::in(['user', 'assistant'])],
            'history.*.content' => ['required', 'string', 'max:1000'],
        ];
    }
}
