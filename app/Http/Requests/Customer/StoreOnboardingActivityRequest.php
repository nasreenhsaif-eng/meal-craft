<?php

namespace App\Http\Requests\Customer;

use App\Enums\CustomerActivityLevel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOnboardingActivityRequest extends FormRequest
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
            'activity_level' => ['required', Rule::enum(CustomerActivityLevel::class)],
        ];
    }
}
