<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDietaryTagRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tagId = $this->route('dietary_tag')->dietary_tag_id;

        return [
            'tag_name' => ['required', 'string', 'max:30', Rule::unique('dietary_tag', 'tag_name')->ignore($tagId, 'dietary_tag_id')],
            'description' => ['nullable', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
