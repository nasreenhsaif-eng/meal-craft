<?php

namespace App\Http\Requests\Customer;

use App\Enums\CycleProtocolMode;
use App\Enums\OnboardingStep;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOnboardingProtocolSelectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null || ! $user->canAccessCustomerPortal()) {
            return false;
        }

        return OnboardingStep::shouldShowPeriodTracking($user->customerProfile);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'protocol_mode' => ['required', 'string', Rule::in(array_column(CycleProtocolMode::cases(), 'value'))],
        ];
    }
}
