<?php

namespace App\Http\Requests\Admin;

use App\Services\MealPlanDefaultDaySelections;
use Illuminate\Foundation\Http\FormRequest;

class StoreMealPlanDefaultDaySelectionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'selections' => ['required', 'array'],
            'selections.*' => ['array'],
            'selections.*.breakfasts' => ['sometimes', 'array'],
            'selections.*.breakfasts.*' => ['integer', 'min:1'],
            'selections.*.meals' => ['sometimes', 'array'],
            'selections.*.meals.*' => ['integer', 'min:1'],
            'selections.*.sideSalads' => ['sometimes', 'array'],
            'selections.*.sideSalads.*' => ['integer', 'min:1'],
            'selections.*.desserts' => ['sometimes', 'array'],
            'selections.*.desserts.*' => ['integer', 'min:1'],
            'selections.*.soup' => ['sometimes', 'array'],
            'selections.*.soup.*' => ['integer', 'min:1'],
        ];
    }

    /**
     * @return array<int, array<string, list<int>>>
     */
    public function normalizedSelections(): array
    {
        /** @var array<int|string, mixed> $selections */
        $selections = $this->validated('selections');

        return MealPlanDefaultDaySelections::normalizeSelectionsMap($selections);
    }
}
