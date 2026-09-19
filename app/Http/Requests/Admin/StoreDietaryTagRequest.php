<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDietaryTagRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tag_name' => ['required', 'string', 'max:30', Rule::unique('dietary_tag', 'tag_name')],
            'description' => ['nullable', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
