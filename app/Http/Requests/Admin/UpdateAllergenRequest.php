<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAllergenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $allergenId = $this->route('allergen')->allergen_id;

        return [
            'allergen_name' => ['required', 'string', 'max:50', Rule::unique('allergen', 'allergen_name')->ignore($allergenId, 'allergen_id')],
            'description' => ['nullable', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
