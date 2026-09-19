<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $categoryId = $this->route('category')->category_id;

        return [
            'category_name' => ['required', 'string', 'max:60'],
            'display_order' => ['nullable', 'integer', 'min:0'],
            'parent_category_id' => [
                'nullable',
                Rule::notIn([$categoryId]),
                Rule::exists('menu_category', 'category_id')->where(fn ($query) => $query->whereNull('parent_category_id')),
            ],
        ];
    }
}
