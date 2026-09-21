<?php

namespace App\Http\Requests\Customer;

use App\Models\AddOnGroup;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class AddCartLineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('add_on_option_ids') && is_array($this->add_on_option_ids)) {
            $this->merge([
                'add_on_option_ids' => array_map('intval', $this->add_on_option_ids),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'item_id' => ['required', Rule::exists('menu_item', 'item_id')],
            'size_id' => ['required', Rule::exists('menu_item_size', 'size_id')],
            'quantity' => ['required', 'integer', 'min:1'],
            'special_request' => ['nullable', 'string', 'max:200'],
            'add_on_option_ids' => ['array'],
            'add_on_option_ids.*' => [Rule::exists('add_on_option', 'option_id')],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $itemId = $this->input('item_id');

            if ($itemId === null) {
                return;
            }

            $optionIds = array_map('intval', (array) $this->input('add_on_option_ids', []));
            $groups = AddOnGroup::where('item_id', $itemId)->with('options')->get();
            $validOptionIds = $groups->pluck('options')->flatten()->pluck('option_id')->map(fn ($id) => (int) $id)->all();

            foreach ($optionIds as $optionId) {
                if (! in_array($optionId, $validOptionIds, true)) {
                    $validator->errors()->add('add_on_option_ids', 'One of the selected options does not belong to this item.');

                    return;
                }
            }

            foreach ($groups as $group) {
                $selected = $group->options->pluck('option_id')->intersect($optionIds)->count();

                if ($selected < $group->min_select) {
                    $validator->errors()->add('add_on_option_ids', "\"{$group->group_name}\" needs at least {$group->min_select} selection(s).");
                }

                if ($selected > $group->max_select) {
                    $validator->errors()->add('add_on_option_ids', "\"{$group->group_name}\" allows at most {$group->max_select} selection(s).");
                }
            }
        });
    }
}
