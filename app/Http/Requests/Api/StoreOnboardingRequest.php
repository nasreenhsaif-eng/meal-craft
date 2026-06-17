<?php

namespace App\Http\Requests\Api;

use App\Enums\CustomerActivityLevel;
use App\Enums\CustomerSex;
use App\Enums\MacroSplitStyle;
use App\Services\Nutrition\UserPlanCalculator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOnboardingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'weight_kg' => ['required', 'numeric', 'min:30', 'max:300'],
            'height_cm' => ['required', 'numeric', 'min:100', 'max:250'],
            'age' => ['required', 'integer', 'min:16', 'max:100'],
            'sex' => ['required', Rule::enum(CustomerSex::class)],
            'activity_level' => ['required', Rule::enum(CustomerActivityLevel::class)],
            'macro_split_style' => ['required', Rule::enum(MacroSplitStyle::class)],
            'daily_calorie_target' => ['nullable', 'integer', Rule::in(UserPlanCalculator::planTiers())],
        ];
    }
}
