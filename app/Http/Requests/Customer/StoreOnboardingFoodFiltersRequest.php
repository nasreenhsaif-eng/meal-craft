<?php

namespace App\Http\Requests\Customer;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOnboardingFoodFiltersRequest extends FormRequest
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
            'allergies' => ['nullable', 'array'],
            'allergies.*' => [
                'string',
                Rule::in(['dairy', 'gluten', 'eggs', 'soy', 'nightshades', 'beans', 'nuts', 'spicy', 'shellfish', 'other']),
            ],
        ];
    }
}
