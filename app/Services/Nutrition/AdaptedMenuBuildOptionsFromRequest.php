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
     *     soup_calories?: float,
     *     side_salad_calories?: float,
     *     dessert_calories?: float,
     *     day_of_week?: int,
     *     craft_key?: string,
     *     plan_tier?: float,
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
            'fixed_chia_breakfast' => ['sometimes', 'boolean'],
        ]);

        $buildOptions = [
            'include_soup' => $includeSoup,
        ];

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

        if (array_key_exists('fixed_chia_breakfast', $validated)) {
            $buildOptions['fixed_chia_breakfast'] = (bool) $validated['fixed_chia_breakfast'];
        }

        return AdaptedMenuFixedPortionResolver::mergeIntoBuildOptions($buildOptions);
    }
}
