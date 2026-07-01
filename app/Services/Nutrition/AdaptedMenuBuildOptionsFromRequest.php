<?php

namespace App\Services\Nutrition;

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
     *     plan_tier?: float,
     *     selected_main_meal_ids?: list<int>,
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
            'plan_tier' => ['sometimes', 'integer', Rule::in(UserPlanCalculator::planTiers())],
            'selected_fixed_slots' => ['sometimes', 'array'],
            'selected_fixed_slots.*' => ['string', Rule::in(UserPlanCalculator::fixedChoiceSlots())],
            'fixed_slot_actual_macros' => ['sometimes', 'array'],
            'fixed_slot_actual_macros.protein_g' => ['sometimes', 'numeric', 'min:0'],
            'fixed_slot_actual_macros.carbs_g' => ['sometimes', 'numeric', 'min:0'],
            'fixed_slot_actual_macros.fat_g' => ['sometimes', 'numeric', 'min:0'],
            'selected_main_meal_ids.*' => ['integer', 'min:1'],
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

        $isAdminPreview = $user->isAdmin() && $user->isCustomer() !== true;

        if (isset($validated['plan_tier']) && $isAdminPreview) {
            $buildOptions['plan_tier'] = (float) (int) $validated['plan_tier'];
        }

        if (isset($validated['selected_main_meal_ids'])) {
            $buildOptions['selected_main_meal_ids'] = array_values(array_unique(array_map(
                static fn (mixed $id): int => (int) $id,
                $validated['selected_main_meal_ids'],
            )));
        }

        if (isset($validated['fixed_slot_actual_macros']) && is_array($validated['fixed_slot_actual_macros'])) {
            $buildOptions['fixed_slot_actual_macros'] = UserPlanCalculator::normalizeMacroGrams($validated['fixed_slot_actual_macros']);
        }

        return AdaptedMenuFixedPortionResolver::mergeIntoBuildOptions($buildOptions);
    }
}
