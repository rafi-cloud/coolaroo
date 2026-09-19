<?php

namespace App\Http\Requests\Admin;

use App\Services\MenuImageService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMenuItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'item_name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
            'category_id' => ['required', Rule::exists('menu_category', 'category_id')],
            'destination' => ['required', Rule::in(['kitchen', 'bar'])],
            'prep_minutes' => ['nullable', 'integer', 'min:1', 'max:255'],
            'is_featured' => ['sometimes', 'boolean'],
            'image' => MenuImageService::rules(required: true),
            'allergens' => ['array'],
            'allergens.*' => [Rule::exists('allergen', 'allergen_id')],
            'dietary_tags' => ['array'],
            'dietary_tags.*' => [Rule::exists('dietary_tag', 'dietary_tag_id')],
            'calories_kcal' => ['nullable', 'numeric', 'min:0'],
            'protein_g' => ['nullable', 'numeric', 'min:0'],
            'carbohydrates_g' => ['nullable', 'numeric', 'min:0'],
            'fat_g' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
