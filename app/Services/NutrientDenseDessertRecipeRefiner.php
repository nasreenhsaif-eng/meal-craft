<?php

namespace App\Services;

use App\Models\Meal;

/**
 * Ensures nutrient-dense baked desserts and Greek yogurt chia variants are tagged for the protocol deck.
 */
final class NutrientDenseDessertRecipeRefiner
{
    /**
     * @return list<string>
     */
    public function refine(): array
    {
        $names = NutrientDenseWeeklyRotationSchedule::NUTRIENT_DENSE_DESSERTS;

        app(BalancedRotationMealRecipeRefiner::class)->refine(
            BalancedRotationMealRecipeRefiner::SALTED_TAHINI_CARAMEL_CHOCOLATE_BAR_NAME,
        );
        app(BalancedCanonicalMealRecipeRefiner::class)->refine(
            BalancedCanonicalMealRecipeRefiner::CARROT_DESSERT_NAME,
        );

        $updated = [];

        foreach ($names as $name) {
            $meal = Meal::queryForMealLibrary()->where('name', $name)->first();

            if ($meal === null) {
                continue;
            }

            $tags = is_array($meal->meal_plan_tags) ? $meal->meal_plan_tags : [];

            if (! in_array('NutrientDense', $tags, true)) {
                $tags[] = 'NutrientDense';
                $meal->update([
                    'meal_plan_tags' => array_values(array_unique($tags)),
                    'meal_plan_tag' => $meal->meal_plan_tag ?? 'NutrientDense',
                ]);
            }

            $updated[] = $name;
        }

        return $updated;
    }
}
