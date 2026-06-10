<?php

namespace App\Http\Requests\Customer;

use App\Enums\DietProtocol;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOnboardingDietProtocolRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canAccessCustomerPortal() ?? false;
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('diet_protocol') === 'ketogenic') {
            $this->merge(['diet_protocol' => 'ketobiotic']);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'diet_protocol' => [
                'required',
                'string',
                Rule::enum(DietProtocol::class),
            ],
        ];
    }
}
