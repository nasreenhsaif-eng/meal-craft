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
        $protocol = $this->input('diet_protocol');

        if (! is_string($protocol) || $protocol === '') {
            return;
        }

        $aliases = [
            'ketogenic' => DietProtocol::Ketobiotic->value,
            'sickle_cell' => DietProtocol::SickleCellWarrior->value,
            'nutrient_density' => DietProtocol::NutrientDense->value,
        ];

        if (isset($aliases[$protocol])) {
            $this->merge(['diet_protocol' => $aliases[$protocol]]);
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
