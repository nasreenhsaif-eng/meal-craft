<?php

namespace App\Http\Requests\Customer;

use App\Enums\CustomerSex;
use App\Enums\CycleProtocolMode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePeriodTrackerCycleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null || ! $user->canAccessCustomerPortal()) {
            return false;
        }

        return $user->customerProfile?->sex === CustomerSex::Female;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'logged_periods' => ['present', 'array', 'min:1'],
            'logged_periods.*.start' => ['required', 'date_format:Y-m-d'],
            'logged_periods.*.end' => ['required', 'date_format:Y-m-d', 'after_or_equal:logged_periods.*.start'],
            'average_cycle_length' => ['required', 'integer', 'min:21', 'max:45'],
            'protocol_mode' => ['required', 'string', Rule::enum(CycleProtocolMode::class)],
        ];
    }
}
