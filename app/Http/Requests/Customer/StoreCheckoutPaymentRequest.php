<?php

namespace App\Http\Requests\Customer;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreCheckoutPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canAccessCustomerPortal() ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'cart_confirmed' => ['accepted'],
            'invoice_same_address' => ['sometimes', 'boolean'],
            'payment_method' => ['required', 'string', 'in:benefit_pay'],
            'promo_code' => ['nullable', 'string', 'max:40'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'cart_confirmed' => filter_var($this->input('cart_confirmed'), FILTER_VALIDATE_BOOLEAN),
            'invoice_same_address' => filter_var($this->input('invoice_same_address', true), FILTER_VALIDATE_BOOLEAN),
        ]);
    }
}
