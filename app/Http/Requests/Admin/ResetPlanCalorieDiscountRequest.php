<?php

namespace App\Http\Requests\Admin;

use App\Support\SubscriptionPlanCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ResetPlanCalorieDiscountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'apply_all' => ['sometimes', 'boolean'],
            'plan_keys' => ['sometimes', 'array'],
            'plan_keys.*' => ['string', Rule::in(array_column(SubscriptionPlanCatalog::plans(), 'key'))],
            'calorie_tiers' => ['sometimes', 'array'],
            'calorie_tiers.*' => ['integer', Rule::in(array_keys(SubscriptionPlanCatalog::baseline()))],
            'days_counts' => ['sometimes', 'array'],
            'days_counts.*' => ['integer', Rule::in(SubscriptionPlanCatalog::DAYS)],
        ];
    }
}
