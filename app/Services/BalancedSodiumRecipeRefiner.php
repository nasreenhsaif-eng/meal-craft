<?php

namespace App\Services;

use App\Models\Ingredient;
use App\Models\Meal;
use App\Support\MealLibraryEditGuard;
use App\Support\StandardMeatPortion;
use Illuminate\Support\Facades\DB;

/**
 * Lowers sodium across Balanced weekly rotation meals so a full day stays within 100% RDI (~2300 mg).
 */
final class BalancedSodiumRecipeRefiner
{
    private const DAILY_SODIUM_RDI_MG = 2300.0;

    /**
     * Restore primary meat when repeated non-idempotent scaling (or bad imports) collapsed it.
     * Below this fraction of {@see StandardMeatPortion::GRAMS}, grams are reset to the standard portion.
     */
    private const COLLAPSED_PRIMARY_MEAT_FRACTION = 0.5;

    /** Meals that keep signature bases (chimichurri, steamed rice, pomegranate sauce, bone broth cup) as authored. */
    private const MEALS_SKIP_SODIUM_ADJUSTMENT = [
        BalancedCanonicalMealRecipeRefiner::BAKED_SALMON_NAME,
        BalancedRotationMealRecipeRefiner::ROASTED_POMEGRANATE_CHICKEN_NAME,
        BalancedMealLibraryConfigurator::BONE_BROTH_MEAL_NAME,
    ];

    /** @var list<string> Ingredients removed entirely from rotation meals. */
    private const REMOVED_INGREDIENTS = [
        'Sea Salt',
        'Tamari Sauce',
        'Tamari',
        'Miso Paste',
        'Miso',
        'Cucumber Pickle (Base)',
    ];

    /**
     * Ingredient name => multiplier applied to grams (0 removes).
     *
     * Primary meat / meat bases are intentionally omitted — sodium cuts belong in the base recipe,
     * not by shrinking the plate's protein portion (repeated runs used to collapse 150 g → ~2 g).
     *
     * @var array<string, float>
     */
    private const SODIUM_SCALE = [
        'Red Pepper Dressing (Base)' => 0.45,
        'Honey Mustard Dressing (Base)' => 0.45,
        'Sumac Za\'atar Dressing (Base)' => 0.35,
        'Zesty Lime Chili Salad Dressing (Base)' => 0.35,
        'Ratatouille (Base)' => 0.0,
        'Turmeric Rice (Base)' => 0.0,
        'Steamed Basmati Rice (Base)' => 0.0,
        'Cooked Quinoa (Base)' => 0.0,
        'Cooked Brown Basmati Rice (Base)' => 0.0,
        'Cooked White Basmati Rice (Base)' => 0.0,
        'Cooked Couscous (Base)' => 0.0,
        'Cooked Chickpeas (Base)' => 0.0,
        'Quinoa Bread (Base)' => 0.65,
        'Quinoa Flatbread (Base)' => 0.65,
        'Bone Broth (Base)' => 0.5,
        'Harissa Paste (Base)' => 0.35,
        'Pickled Red Onion (Base)' => 0.0,
        'Slaw (Base)' => 0.0,
    ];

    /**
     * Generous unscaled ceilings used to apply {@see SODIUM_SCALE} idempotently:
     * target = min(current, ceiling) × factor; skip when already at/below that target.
     *
     * @var array<string, float>
     */
    private const SODIUM_SCALE_UNSCALED_CEILING = [
        'Red Pepper Dressing (Base)' => 40.0,
        'Honey Mustard Dressing (Base)' => 40.0,
        'Sumac Za\'atar Dressing (Base)' => 40.0,
        'Zesty Lime Chili Salad Dressing (Base)' => 40.0,
        'Quinoa Bread (Base)' => 60.0,
        'Quinoa Flatbread (Base)' => 60.0,
        'Bone Broth (Base)' => 350.0,
        'Harissa Paste (Base)' => 20.0,
    ];

    /**
     * Unscaled ceilings for stock/broth → water swap (retain 25%, move the rest to water).
     *
     * @var array<string, float>
     */
    private const STOCK_WATER_SWAP_CEILING = [
        'Vegetable Stock' => 60.0,
        'Vegetable Broth (Base)' => 60.0,
    ];

    /**
     * When a scaled ingredient is removed, add low-sodium replacements (grams).
     *
     * @var array<string, array<string, float>>
     */
    private const REPLACEMENTS = [
        'Cooked Quinoa (Base)' => ['Quinoa (White)' => 30.0],
        'Cooked Brown Basmati Rice (Base)' => ['Basmati Rice (Brown)' => 45.0],
        'Cooked White Basmati Rice (Base)' => ['Basmati Rice (White)' => 45.0],
        'Cooked Couscous (Base)' => ['Couscous' => 30.0],
        'Cooked Chickpeas (Base)' => ['Chickpeas' => 75.0],
        'Turmeric Rice (Base)' => ['Basmati Rice (Brown)' => 45.0, 'Turmeric Powder' => 1.0],
        'Steamed Basmati Rice (Base)' => ['Basmati Rice (Brown)' => 45.0],
        'Ratatouille (Base)' => [
            'Zucchini' => 40.0,
            'Bell Pepper (Red)' => 35.0,
            'Tomato (Raw)' => 45.0,
            'Eggplant' => 35.0,
            'Fresh Basil' => 4.0,
        ],
        'Pickled Red Onion (Base)' => [
            'Red Onion' => 15.0,
            'Apple Cider Vinegar' => 5.0,
        ],
        'Slaw (Base)' => [
            'Cabbage (Purple)' => 30.0,
            'Carrots' => 20.0,
            'Lemon Juice' => 5.0,
        ],
    ];

    /**
     * @return list<string>
     */
    public function refine(): array
    {
        return DB::transaction(function (): array {
            $updated = [];

            $mealNames = array_values(array_unique(array_merge(
                BalancedWeeklyRotationSchedule::allScheduledMealNames(),
                $this->libraryMealNamesWithCollapsedPrimaryMeat(),
            )));

            foreach ($mealNames as $mealName) {
                if (in_array($mealName, self::MEALS_SKIP_SODIUM_ADJUSTMENT, true)) {
                    continue;
                }

                /** @var Meal|null $meal */
                $meal = Meal::queryForMealLibrary()->where('name', $mealName)->first();

                if ($meal === null) {
                    continue;
                }

                if (MealLibraryEditGuard::shouldSkipMealRefinement($meal)
                    && ! MealLibraryEditGuard::mealHasCollapsedPrimaryMeat($meal)) {
                    continue;
                }

                $meal->load('ingredients');

                /** @var array<string, float> $ingredientGrams */
                $ingredientGrams = [];

                foreach ($meal->ingredients as $ingredient) {
                    $grams = (float) ($ingredient->pivot->amount_grams ?? $ingredient->pivot->amount ?? 0);

                    if ($grams <= 0) {
                        continue;
                    }

                    $ingredientGrams[$ingredient->name] = ($ingredientGrams[$ingredient->name] ?? 0) + $grams;
                }

                $adjusted = $this->adjustIngredientGrams($ingredientGrams, $mealName);

                if ($adjusted === $ingredientGrams) {
                    continue;
                }

                $this->syncMeal($meal, $adjusted);
                $updated[] = $mealName;
            }

            return $updated;
        });
    }

    /**
     * @return list<string>
     */
    private function libraryMealNamesWithCollapsedPrimaryMeat(): array
    {
        $names = [];

        foreach (Meal::queryForMealLibrary()->with('ingredients')->cursor() as $meal) {
            if (MealLibraryEditGuard::mealHasCollapsedPrimaryMeat($meal)) {
                $names[] = $meal->name;
            }
        }

        return $names;
    }

    /**
     * @param  array<string, float>  $ingredientGrams
     * @return array<string, float>
     */
    public function adjustIngredientGrams(array $ingredientGrams, ?string $mealName = null): array
    {
        $ingredientGrams = $this->restoreCollapsedPrimaryMeatPortions($ingredientGrams, $mealName);

        foreach (self::REMOVED_INGREDIENTS as $removed) {
            if (! isset($ingredientGrams[$removed])) {
                continue;
            }

            unset($ingredientGrams[$removed]);

            foreach (self::REPLACEMENTS[$removed] ?? [] as $replacement => $grams) {
                $ingredientGrams[$replacement] = ($ingredientGrams[$replacement] ?? 0) + $grams;
            }
        }

        foreach (self::SODIUM_SCALE as $ingredientName => $multiplier) {
            if (! isset($ingredientGrams[$ingredientName])) {
                continue;
            }

            if (StandardMeatPortion::isPrimaryMeatIngredient($ingredientName, $mealName)) {
                continue;
            }

            $original = $ingredientGrams[$ingredientName];
            unset($ingredientGrams[$ingredientName]);

            if ($multiplier <= 0) {
                foreach (self::REPLACEMENTS[$ingredientName] ?? [] as $replacement => $grams) {
                    $ingredientGrams[$replacement] = ($ingredientGrams[$replacement] ?? 0) + $grams;
                }

                continue;
            }

            $ceiling = self::SODIUM_SCALE_UNSCALED_CEILING[$ingredientName] ?? $original;
            $scaledCeilingTarget = round($ceiling * $multiplier, 4);

            // Already at/below the sodium target from a prior refine — do not shrink again.
            if ($original <= $scaledCeilingTarget + 0.05) {
                $ingredientGrams[$ingredientName] = $original;

                continue;
            }

            $scaled = round($original * $multiplier, 4);

            if ($scaled > 0) {
                $ingredientGrams[$ingredientName] = $scaled;
            }
        }

        foreach (['Vegetable Stock', 'Vegetable Broth (Base)'] as $stockName) {
            if (! isset($ingredientGrams[$stockName])) {
                continue;
            }

            $stockGrams = $ingredientGrams[$stockName];
            $ceiling = self::STOCK_WATER_SWAP_CEILING[$stockName] ?? $stockGrams;
            $scaledCeilingTarget = round($ceiling * 0.25, 4);

            // Idempotent: stock already looks like a post-swap retained amount.
            if ($stockGrams <= $scaledCeilingTarget + 0.05) {
                continue;
            }

            $retainedTarget = round($stockGrams * 0.25, 4);
            $waterSwap = round($stockGrams - $retainedTarget, 4);
            $ingredientGrams[$stockName] = $retainedTarget;
            $ingredientGrams['Water (Filtered)'] = ($ingredientGrams['Water (Filtered)'] ?? 0) + $waterSwap;
        }

        return $ingredientGrams;
    }

    /**
     * @param  array<string, float>  $ingredientGrams
     * @return array<string, float>
     */
    private function restoreCollapsedPrimaryMeatPortions(array $ingredientGrams, ?string $mealName = null): array
    {
        $floor = StandardMeatPortion::GRAMS * self::COLLAPSED_PRIMARY_MEAT_FRACTION;

        foreach ($ingredientGrams as $name => $grams) {
            if (! StandardMeatPortion::isPrimaryMeatIngredient($name, $mealName)) {
                continue;
            }

            if (StandardMeatPortion::isLiverBlendIngredient($name, $mealName)) {
                continue;
            }

            if ($grams <= 0.0 || $grams >= $floor) {
                continue;
            }

            $target = str_contains(strtolower($name), 'beef')
                ? StandardMeatPortion::targetPrimaryBeefGrams(
                    $this->ingredientGramsAsPivotIterable($ingredientGrams),
                    $mealName,
                )
                : StandardMeatPortion::GRAMS;

            $ingredientGrams[$name] = $target;
        }

        return $ingredientGrams;
    }

    /**
     * @param  array<string, float>  $ingredientGrams
     * @return list<object>
     */
    private function ingredientGramsAsPivotIterable(array $ingredientGrams): array
    {
        $rows = [];

        foreach ($ingredientGrams as $name => $grams) {
            $rows[] = (object) [
                'name' => $name,
                'pivot' => (object) ['amount_grams' => $grams],
            ];
        }

        return $rows;
    }

    public static function dailySodiumRdiMg(): float
    {
        return self::DAILY_SODIUM_RDI_MG;
    }

    /**
     * @param  array<string, float>  $ingredientGrams
     */
    private function syncMeal(Meal $meal, array $ingredientGrams): void
    {
        $sync = [];

        foreach ($ingredientGrams as $ingredientName => $grams) {
            if ($grams <= 0) {
                continue;
            }

            /** @var Ingredient|null $ingredient */
            $ingredient = Ingredient::query()->where('name', $ingredientName)->first();

            if ($ingredient === null) {
                continue;
            }

            $sync[$ingredient->id] = [
                'amount_grams' => round((float) $grams, 4),
                'amount' => round((float) $grams, 4),
                'unit' => 'g',
            ];
        }

        $meal->ingredients()->sync($sync);

        $fresh = $meal->fresh(['ingredients']);
        $nutrition = RecipeNutritionCalculator::fromMeal($fresh);

        $meal->update(array_merge(
            Meal::nutritionSummaryToPersistedAttributes($nutrition),
            ['nutrition_aggregates_synced' => true],
        ));

        MealRecipeAsIngredientSyncService::syncFromPersistedMeal($fresh->fresh(['ingredients']), false);
    }
}
