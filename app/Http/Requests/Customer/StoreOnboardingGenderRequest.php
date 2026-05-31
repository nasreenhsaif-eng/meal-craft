<?php

namespace App\Http\Requests\Customer;

use App\Enums\CustomerSex;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOnboardingGenderRequest extends FormRequest
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
            'sex' => ['required', Rule::enum(CustomerSex::class)],
        ];
    }
}
