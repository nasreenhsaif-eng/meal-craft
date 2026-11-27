<?php

namespace App\Http\Requests\Customer;

use App\Enums\CycleProtocolMode;
use App\Enums\DietProtocol;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCycleProtocolModeRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null || ! $user->canAccessCustomerPortal()) {
            return false;
        }

        $profile = $user->customerProfile;

        if ($profile === null) {
            return false;
        }

        return DietProtocol::tryFromStored($profile->diet_protocol) === DietProtocol::CycleSync;
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
