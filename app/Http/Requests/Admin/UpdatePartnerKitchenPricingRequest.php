<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePartnerKitchenPricingRequest extends FormRequest
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
            'prices' => ['required', 'array', 'min:1'],
            'prices.*.id' => ['required', 'integer', 'exists:plan_calorie_prices,id'],
            'prices.*.base_total_price' => ['required', 'integer', 'min:0'],
            'prices.*.discounted_total_price' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
