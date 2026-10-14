<?php

namespace App\Http\Requests\Customer;

use Illuminate\Foundation\Http\FormRequest;

class StoreOnboardingBirthdayRequest extends FormRequest
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
        $minimumBirthDate = now()->subYears(100)->startOfYear()->toDateString();
        $maximumBirthDate = now()->subYears(13)->toDateString();

        return [
            'date_of_birth' => [
                'required',
                'date',
                'after_or_equal:'.$minimumBirthDate,
                'before_or_equal:'.$maximumBirthDate,
            ],
        ];
    }
}
