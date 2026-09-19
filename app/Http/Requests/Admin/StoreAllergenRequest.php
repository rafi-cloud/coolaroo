<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAllergenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'allergen_name' => ['required', 'string', 'max:50', Rule::unique('allergen', 'allergen_name')],
            'description' => ['nullable', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
