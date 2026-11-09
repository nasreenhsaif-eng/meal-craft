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
            'cards' => ['required', 'array', 'min:1'],
            'cards.*.id' => ['required', 'integer', 'exists:partner_kitchen_price_cards,id'],
            'cards.*.amount_bhd' => ['required', 'numeric', 'min:0'],
            'cards.*.discount_type' => ['sometimes', 'string', 'in:amount,percent'],
            'cards.*.discount_bhd' => ['sometimes', 'numeric', 'min:0'],
            'limited_time_discount' => ['sometimes', 'numeric', 'min:0'],
            'limited_time_discount_label' => ['sometimes', 'string', 'max:120'],
            'delivery_cost' => ['sometimes', 'numeric', 'min:0'],
        ];
    }
}
