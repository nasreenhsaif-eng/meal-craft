<?php

namespace App\Http\Requests\Customer;

use App\Enums\OnboardingStep;
use Illuminate\Foundation\Http\FormRequest;

class StoreOnboardingActivePhaseRequest extends FormRequest
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
        return [];
    }
}
