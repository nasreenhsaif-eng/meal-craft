<?php

namespace App\Http\Requests\Customer;

use App\Support\AdminConsultationPreviewProfile;
use App\Support\CustomerIntake;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreCheckoutDetailsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canAccessCustomerPortal() ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(CustomerIntake::prepareCheckout($this->all()));
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $user = $this->user();
        $profile = $user !== null ? AdminConsultationPreviewProfile::resolve($user) : null;

        return CustomerIntake::checkoutRules($profile);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'phone.regex' => __('Enter a valid mobile number, including the country code.'),
            'phone.unique' => __('This mobile number is already registered.'),
        ];
    }
}
