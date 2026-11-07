<?php

namespace App\Http\Requests\Customer;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCheckoutPlanWeeksRequest extends FormRequest
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
            'plan_weeks' => ['required', 'integer', Rule::in([1, 4])],
        ];
    }
}
