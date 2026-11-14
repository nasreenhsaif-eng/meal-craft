<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreConsultationResumeDraftRequest extends FormRequest
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
        return [
            'craftKey' => ['required', 'string', Rule::in(['full', 'day', 'afternoon', 'intermittent', 'business'])],
            'weekDuration' => ['required', 'integer', 'min:1', 'max:7'],
            'planWeeks' => ['sometimes', 'integer', Rule::in([1, 4])],
            'selectedBilledDays' => ['sometimes', 'integer', Rule::in([5, 6, 7, 20, 24, 28])],
            'selectedDays' => ['required', 'array', 'min:1'],
            'selectedDays.*' => ['integer', 'min:1', 'max:7'],
            'fulfillmentChoice' => ['required', 'string', Rule::in(['diy', 'partner_kitchen'])],
            'selectedByDay' => ['sometimes', 'array'],
            'businessSideChoiceByDay' => ['sometimes', 'array'],
            'afternoonSoupOptInByDay' => ['sometimes', 'array'],
            'fullCraftSoupOptInByDay' => ['sometimes', 'array'],
            'environmentYes' => ['sometimes', 'boolean'],
        ];
    }
}
