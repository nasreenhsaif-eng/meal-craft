<?php

namespace App\Http\Requests\Admin;

use App\Models\CustomerProfile;
use App\Support\CustomerIntake;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCustomerProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
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
        $customer = $this->route('customer');
        $userId = $customer instanceof CustomerProfile ? $customer->user_id : null;

        return CustomerIntake::rules($userId, false);
    }
}
