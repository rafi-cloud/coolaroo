<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tableId = $this->route('table')->table_id;

        return [
            'table_number' => ['required', 'string', 'max:10', Rule::unique('restaurant_table', 'table_number')->ignore($tableId, 'table_id')],
            'seat_capacity' => ['required', 'integer', 'min:1'],
            'section' => ['nullable', 'string', 'max:20'],
        ];
    }
}
