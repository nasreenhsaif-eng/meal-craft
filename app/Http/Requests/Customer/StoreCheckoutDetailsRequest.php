<?php

namespace App\Http\Requests\Customer;

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
        $this->merge(CustomerIntake::prepare($this->all()));
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return CustomerIntake::rules($this->user()?->id, true);
    }
}
