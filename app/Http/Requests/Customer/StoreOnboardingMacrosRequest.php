<?php

namespace App\Http\Requests\Customer;

use App\Enums\MacroSplitStyle;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOnboardingMacrosRequest extends FormRequest
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
            'macro_split_style' => ['required', Rule::enum(MacroSplitStyle::class)],
            'daily_calorie_target' => ['nullable', 'integer', 'min:1200', 'max:6000'],
        ];
    }
}
