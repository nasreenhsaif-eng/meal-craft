<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCustomerCraftPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canAccessCustomerPortal() ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'craft_key' => ['required', 'string', Rule::in(['full', 'day', 'afternoon', 'intermittent', 'business'])],
            'week_duration' => ['required', 'integer', 'min:1', 'max:7'],
            'selected_days' => ['required', 'array', 'min:1'],
            'selected_days.*' => ['integer', 'min:1', 'max:7'],
            'days' => ['required', 'array', 'min:1'],
            'days.*.day_of_week' => ['required', 'integer', 'min:1', 'max:7'],
            'days.*.include_soup' => ['sometimes', 'boolean'],
            'days.*.selections' => ['required', 'array'],
            'days.*.selections.breakfasts' => ['sometimes', 'array'],
            'days.*.selections.breakfasts.*' => ['integer', 'exists:meals,id'],
            'days.*.selections.meals' => ['sometimes', 'array'],
            'days.*.selections.meals.*' => ['integer', 'exists:meals,id'],
            'days.*.selections.sideSalads' => ['sometimes', 'array'],
            'days.*.selections.sideSalads.*' => ['integer', 'exists:meals,id'],
            'days.*.selections.desserts' => ['sometimes', 'array'],
            'days.*.selections.desserts.*' => ['integer', 'exists:meals,id'],
            'days.*.selections.soup' => ['sometimes', 'array'],
            'days.*.selections.soup.*' => ['integer', 'exists:meals,id'],
        ];
    }
}
