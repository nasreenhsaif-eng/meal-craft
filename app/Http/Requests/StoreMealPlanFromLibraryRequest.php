<?php

namespace App\Http\Requests;

use App\Enums\MealCyclePhaseTag;
use App\Enums\MealPlanLibraryCategory;
use App\Enums\MealPlanSlotType;
use App\Models\Meal;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreMealPlanFromLibraryRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'goal' => ['required', 'string', 'max:5000'],
            'plan_category' => ['required', Rule::enum(MealPlanLibraryCategory::class)],
            'cycle_phase' => [
                Rule::requiredIf(fn (): bool => $this->input('plan_category') === MealPlanLibraryCategory::CycleSync->value),
                'nullable',
                Rule::enum(MealCyclePhaseTag::class),
            ],
            'target_daily_calories' => ['required', 'numeric', 'min:1'],
            'target_daily_protein_g' => ['nullable', 'numeric', 'min:0'],
            'target_daily_carbs_g' => ['nullable', 'numeric', 'min:0'],
            'target_daily_fat_g' => ['nullable', 'numeric', 'min:0'],
            'slots' => ['required', 'array', 'size:'.(7 * count(MealPlanSlotType::daySlotTemplate()))],
            'slots.*.day_number' => ['required', 'integer', 'min:1', 'max:7'],
            'slots.*.slot_type' => ['required', 'string', Rule::enum(MealPlanSlotType::class)],
            'slots.*.slot_index' => ['required', 'integer', 'min:1', 'max:4'],
            'slots.*.meal_id' => ['required', 'integer', 'exists:meals,id'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $slots = $this->input('slots', []);
            if (! is_array($slots)) {
                return;
            }

            $expectedKeys = [];
            foreach (range(1, 7) as $dayNumber) {
                foreach (MealPlanSlotType::daySlotTemplate() as [$slotType, $slotIndex]) {
                    $expectedKeys[sprintf('%d:%s:%d', $dayNumber, $slotType->value, $slotIndex)] = true;
                }
            }

            $seenKeys = [];
            foreach ($slots as $index => $slot) {
                if (! is_array($slot)) {
                    continue;
                }

                $dayNumber = (int) ($slot['day_number'] ?? 0);
                $slotTypeRaw = (string) ($slot['slot_type'] ?? '');
                $slotIndex = (int) ($slot['slot_index'] ?? 0);
                $mealId = (int) ($slot['meal_id'] ?? 0);

                $slotType = MealPlanSlotType::tryFrom($slotTypeRaw);
                if ($slotType === null) {
                    continue;
                }

                $key = sprintf('%d:%s:%d', $dayNumber, $slotType->value, $slotIndex);
                if (! isset($expectedKeys[$key])) {
                    $validator->errors()->add(
                        "slots.{$index}",
                        __('Invalid scheduler slot (day :day, :slot :index).', [
                            'day' => $dayNumber,
                            'slot' => $slotType->value,
                            'index' => $slotIndex,
                        ]),
                    );

                    continue;
                }

                if (isset($seenKeys[$key])) {
                    $validator->errors()->add("slots.{$index}", __('Duplicate scheduler slot.'));

                    continue;
                }

                $seenKeys[$key] = true;

                $meal = Meal::query()->find($mealId);
                if ($meal === null) {
                    continue;
                }

                if ($meal->meal_type !== $slotType->mealType()) {
                    $validator->errors()->add(
                        "slots.{$index}",
                        __('“:meal” does not match the :slot slot type.', [
                            'meal' => $meal->name,
                            'slot' => $slotType->mealType()->label(),
                        ]),
                    );
                }
            }

            foreach (array_keys($expectedKeys) as $expectedKey) {
                if (! isset($seenKeys[$expectedKey])) {
                    $validator->errors()->add('slots', __('Assign a meal to every scheduler slot for all 7 days before saving.'));

                    break;
                }
            }
        });
    }
}
