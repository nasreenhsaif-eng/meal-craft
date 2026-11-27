<?php

namespace App\Http\Requests\Api;

use App\Enums\CycleProtocolMode;
use App\Enums\CycleSyncPhase;
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
            'fulfillment_choice' => ['required', 'string', Rule::in(['diy', 'partner_kitchen'])],
            'include_cutlery' => [
                Rule::requiredIf(fn (): bool => $this->input('fulfillment_choice') === 'partner_kitchen'),
                'nullable',
                'boolean',
            ],
            'week_duration' => ['required', 'integer', 'min:1', 'max:7'],
            'plan_weeks' => ['sometimes', 'integer', Rule::in([1, 4])],
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
            'cycle_sync' => ['sometimes', 'array'],
            'cycle_sync.protocol_mode' => ['required_with:cycle_sync', 'string', Rule::in(array_column(CycleProtocolMode::cases(), 'value'))],
            'cycle_sync.phase_key' => ['required_with:cycle_sync', 'string', Rule::in(array_column(CycleSyncPhase::cases(), 'value'))],
            'cycle_sync.biological_phase' => ['sometimes', 'string', 'max:120'],
            'cycle_sync.food_approach' => ['sometimes', 'string', 'max:120'],
            'cycle_sync.fasting_window' => ['sometimes', 'string', 'max:120'],
            'cycle_sync.fasting_type' => ['sometimes', 'string', Rule::in(['fasting', 'no_fasting'])],
            'cycle_sync.schedules_full_daily_meals' => ['sometimes', 'boolean'],
            'cycle_sync.craft_key' => ['sometimes', 'string', Rule::in(['full', 'day', 'afternoon', 'intermittent', 'business'])],
        ];
    }
}
