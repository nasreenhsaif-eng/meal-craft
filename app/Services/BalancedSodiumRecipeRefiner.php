<?php

namespace App\Services;

use App\Models\Ingredient;
use App\Models\Meal;
use Illuminate\Support\Facades\DB;

/**
 * Lowers sodium across Balanced weekly rotation meals so a full day stays within 100% RDI (~2300 mg).
 */
final class BalancedSodiumRecipeRefiner
{
    private const DAILY_SODIUM_RDI_MG = 2300.0;

    /** Meals that keep high-sodium signature bases (chimichurri, steamed rice, pomegranate sauce) as authored. */
    private const MEALS_SKIP_SODIUM_ADJUSTMENT = [
        BalancedCanonicalMealRecipeRefiner::BAKED_SALMON_NAME,
        BalancedRotationMealRecipeRefiner::ROASTED_POMEGRANATE_CHICKEN_NAME,
    ];

    /** @var list<string> Ingredients removed entirely from rotation meals. */
    private const REMOVED_INGREDIENTS = [
        'Sea Salt',
        'Tamari Sauce',
        'Tamari',
        'Miso Paste',
        'Miso',
        'Cucumber Pickle (Base)',
        'Tomato Sauce (Sub-Base)',
    ];

    /**
     * Ingredient name => multiplier applied to grams (0 removes).
     *
     * @var array<string, float>
     */
    private const SODIUM_SCALE = [
        'Rosemary Garlic Chicken (Base)' => 0.75,
        'Red Pepper Dressing (Base)' => 0.45,
        'Honey Mustard Dressing (Base)' => 0.45,
        'Sumac Za\'atar Dressing (Base)' => 0.35,
        'Zesty Lime Chili Salad Dressing (Base)' => 0.35,
        'Ratatouille (Base)' => 0.0,
        'Turmeric Rice (Base)' => 0.0,
        'Steamed Basmati Rice (Base)' => 0.0,
        'Quinoa (Base)' => 0.0,
        'Quinoa Bread (Base)' => 0.65,
        'Quinoa Flatbread (Base)' => 0.65,
        'Bone Broth (Base)' => 0.5,
        'Vegetable Stock' => 0.25,
        'Vegetable Broth (Base)' => 0.25,
        'Harissa Paste (Base)' => 0.35,
        'Harissa Paste' => 0.5,
        'Pickled Red Onion (Base)' => 0.0,
        'Slaw (Base)' => 0.0,
    ];

    /**
     * When a scaled ingredient is removed, add low-sodium replacements (grams).
     *
     * @var array<string, array<string, float>>
     */
    private const REPLACEMENTS = [
        'Quinoa (Base)' => ['Quinoa (White)' => 30.0],
        'Turmeric Rice (Base)' => ['Basmati Rice (Brown)' => 45.0, 'Turmeric Powder' => 1.0],
        'Steamed Basmati Rice (Base)' => ['Basmati Rice (Brown)' => 45.0],
        'Ratatouille (Base)' => [
            'Zucchini' => 40.0,
            'Bell Pepper (Red)' => 35.0,
            'Tomato (Raw)' => 45.0,
            'Eggplant' => 35.0,
            'Fresh Basil' => 4.0,
        ],
        'Tomato Sauce (Sub-Base)' => [
            'Tomato (Raw)' => 180.0,
            'Garlic (Raw)' => 4.0,
            'Olive Oil' => 4.0,
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

            foreach (BalancedWeeklyRotationSchedule::allScheduledMealNames() as $mealName) {
                if (in_array($mealName, self::MEALS_SKIP_SODIUM_ADJUSTMENT, true)) {
                    continue;
                }

                /** @var Meal|null $meal */
                $meal = Meal::queryForMealLibrary()->where('name', $mealName)->first();

                if ($meal === null) {
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

                $adjusted = $this->adjustIngredientGrams($ingredientGrams);

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
     * @param  array<string, float>  $ingredientGrams
     * @return array<string, float>
     */
    public function adjustIngredientGrams(array $ingredientGrams): array
    {
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

            $original = $ingredientGrams[$ingredientName];
            unset($ingredientGrams[$ingredientName]);

            if ($multiplier <= 0) {
                foreach (self::REPLACEMENTS[$ingredientName] ?? [] as $replacement => $grams) {
                    $ingredientGrams[$replacement] = ($ingredientGrams[$replacement] ?? 0) + $grams;
                }

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
            $waterSwap = round($stockGrams * 0.75, 4);
            $ingredientGrams[$stockName] = round($stockGrams - $waterSwap, 4);
            $ingredientGrams['Water (Filtered)'] = ($ingredientGrams['Water (Filtered)'] ?? 0) + $waterSwap;
        }

        return $ingredientGrams;
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
