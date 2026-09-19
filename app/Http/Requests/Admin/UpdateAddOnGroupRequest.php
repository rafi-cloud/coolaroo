<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAddOnGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'group_name' => ['required', 'string', 'max:60'],
            'is_required' => ['sometimes', 'boolean'],
            'min_select' => [
                'required', 'integer', 'min:0',
                function ($attribute, $value, $fail) {
                    if ($this->boolean('is_required') && $value < 1) {
                        $fail('Required groups need a minimum selection of at least 1.');
                    }
                },
            ],
            'max_select' => ['required', 'integer', 'gte:min_select'],
        ];
    }
}
