<?php

namespace App\Services\Nutrition;

use App\Enums\CycleProtocolMode;
use App\Enums\CycleSyncPhase;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class AdaptedMenuBuildOptionsFromRequest
{
    /**
     * @return array{
     *     include_soup?: bool,
     *     selected_fixed_slots?: list<string>,
     *     soup_calories?: float,
     *     side_salad_calories?: float,
     *     dessert_calories?: float,
     *     day_of_week?: int,
     *     craft_key?: string,
     *     selected_main_meal_ids?: list<int>,
     *     protocol_mode?: string,
     *     phase_key?: string,
     *     biological_phase?: string,
     *     food_approach?: string,
     *     fasting_window?: string,
     *     fasting_type?: string,
     *     schedules_full_daily_meals?: bool,
     * }
     */
    public static function resolve(Request $request, User $user): array
    {
        $includeSoup = $request->boolean('include_soup');

        $validated = $request->validate([
            'craft_key' => ['sometimes', 'string', Rule::in(CraftCaloriePlanner::keys())],
            'soup_calories' => ['sometimes', 'numeric', 'min:0'],
            'side_salad_calories' => ['sometimes', 'numeric', 'min:0'],
            'dessert_calories' => ['sometimes', 'numeric', 'min:0'],
            'day_of_week' => ['sometimes', 'integer', 'min:1', 'max:7'],
            'selected_fixed_slots' => ['sometimes', 'array'],
            'selected_fixed_slots.*' => ['string', Rule::in(UserPlanCalculator::fixedChoiceSlots())],
            'fixed_slot_actual_macros' => ['sometimes', 'array'],
            'fixed_slot_actual_macros.protein_g' => ['sometimes', 'numeric', 'min:0'],
            'fixed_slot_actual_macros.carbs_g' => ['sometimes', 'numeric', 'min:0'],
            'fixed_slot_actual_macros.fat_g' => ['sometimes', 'numeric', 'min:0'],
            'selected_main_meal_ids' => ['sometimes', 'array'],
            'selected_breakfast_meal_ids' => ['sometimes', 'array'],
            'protocol_mode' => ['sometimes', 'string', Rule::in(array_column(CycleProtocolMode::cases(), 'value'))],
            'phase_key' => ['sometimes', 'string', Rule::in(array_column(CycleSyncPhase::cases(), 'value'))],
            'biological_phase' => ['sometimes', 'string', 'max:120'],
            'food_approach' => ['sometimes', 'string', 'max:120'],
            'fasting_window' => ['sometimes', 'string', 'max:120'],
            'fasting_type' => ['sometimes', 'string', Rule::in(['fasting', 'no_fasting'])],
            'schedules_full_daily_meals' => ['sometimes', 'boolean'],
        ]);

        $selectedFixedSlots = isset($validated['selected_fixed_slots'])
            ? array_values(array_unique($validated['selected_fixed_slots']))
            : null;

        if ($selectedFixedSlots !== null) {
            $includeSoup = in_array('soup', $selectedFixedSlots, true);
        }

        $buildOptions = [
            'include_soup' => $includeSoup,
        ];

        if ($selectedFixedSlots !== null) {
            $buildOptions['selected_fixed_slots'] = $selectedFixedSlots;
        }

        if (isset($validated['soup_calories'])) {
            $buildOptions['soup_calories'] = (float) $validated['soup_calories'];
        }

        if (isset($validated['side_salad_calories'])) {
            $buildOptions['side_salad_calories'] = (float) $validated['side_salad_calories'];
        }

        if (isset($validated['dessert_calories'])) {
            $buildOptions['dessert_calories'] = (float) $validated['dessert_calories'];
        }

        if (isset($validated['day_of_week'])) {
            $buildOptions['day_of_week'] = (int) $validated['day_of_week'];
        }

        if (isset($validated['craft_key'])) {
            $buildOptions['craft_key'] = $validated['craft_key'];
        }

        foreach ([
            'protocol_mode',
            'phase_key',
            'biological_phase',
            'food_approach',
            'fasting_window',
            'fasting_type',
        ] as $cycleKey) {
            if (isset($validated[$cycleKey])) {
                $buildOptions[$cycleKey] = $validated[$cycleKey];
            }
        }

        if (array_key_exists('schedules_full_daily_meals', $validated)) {
            $buildOptions['schedules_full_daily_meals'] = (bool) $validated['schedules_full_daily_meals'];

            if (! isset($buildOptions['craft_key'])) {
                $buildOptions['craft_key'] = $buildOptions['schedules_full_daily_meals']
                    ? CraftCaloriePlanner::CRAFT_FULL
                    : CraftCaloriePlanner::CRAFT_INTERMITTENT;
            }
        }

        $selectedMainMealIds = self::positiveIntegerIds($request->input('selected_main_meal_ids'));

        if ($selectedMainMealIds !== []) {
            $buildOptions['selected_main_meal_ids'] = $selectedMainMealIds;
        }

        $selectedBreakfastMealIds = self::positiveIntegerIds($request->input('selected_breakfast_meal_ids'));

        if ($selectedBreakfastMealIds !== []) {
            $buildOptions['selected_breakfast_meal_ids'] = $selectedBreakfastMealIds;
        }

        if (isset($validated['fixed_slot_actual_macros']) && is_array($validated['fixed_slot_actual_macros'])) {
            $buildOptions['fixed_slot_actual_macros'] = UserPlanCalculator::normalizeMacroGrams($validated['fixed_slot_actual_macros']);
        }

        return AdaptedMenuFixedPortionResolver::mergeIntoBuildOptions($buildOptions);
    }

    /**
     * Keep real meal ids and drop placeholders such as sample-deck ids.
     *
     * @return list<int>
     */
    private static function positiveIntegerIds(mixed $raw): array
    {
        if (! is_array($raw)) {
            return [];
        }

        $ids = [];

        foreach ($raw as $id) {
            if (is_int($id)) {
                $normalized = $id;
            } elseif (is_string($id) && preg_match('/^[1-9]\d*$/', $id) === 1) {
                $normalized = (int) $id;
            } else {
                continue;
            }

            if ($normalized > 0) {
                $ids[] = $normalized;
            }
        }

        return array_values(array_unique($ids));
    }
}
