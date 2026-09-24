<?php

namespace App\Http\Requests\Public;

use Illuminate\Foundation\Http\FormRequest;

class AiMealBuilderRequest extends FormRequest
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
            'budget' => ['required', 'numeric', 'min:1', 'max:1000'],
            'party_size' => ['required', 'integer', 'min:1', 'max:10'],
            'dietary' => ['sometimes', 'array', 'max:6'],
            'dietary.*' => ['string', 'max:40'],
            'preferences' => ['nullable', 'string', 'max:300'],
        ];
    }
}
