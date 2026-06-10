<?php

namespace App\Http\Requests\Customer;

use App\Enums\OnboardingStep;
use Illuminate\Foundation\Http\FormRequest;

class StoreOnboardingPeriodTrackingRequest extends FormRequest
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
            'logged_periods' => ['present', 'array'],
            'logged_periods.*.start' => ['required', 'date_format:Y-m-d'],
            'logged_periods.*.end' => ['required', 'date_format:Y-m-d', 'after_or_equal:logged_periods.*.start'],
            'average_cycle_length' => ['required', 'integer', 'min:21', 'max:45'],
        ];
    }
}
