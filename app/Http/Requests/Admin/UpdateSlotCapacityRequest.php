<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSlotCapacityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $slotId = $this->route('slot')->slot_id;

        return [
            'slot_time' => ['required', 'date_format:H:i', Rule::unique('slot_capacity', 'slot_time')->ignore($slotId, 'slot_id')],
            'max_covers' => ['required', 'integer', 'min:1'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
