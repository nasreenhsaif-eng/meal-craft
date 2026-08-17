<?php

namespace App\Console\Commands;

use App\Models\Meal;
use App\Services\MealLibraryRefinerSourceSync;
use App\Services\MealRecipeAsIngredientSyncService;
use App\Services\MenuDevelopmentCsvSync;
use App\Services\RecipeNutritionCalculator;
use App\Support\KitchenPortionRounding;
use App\Support\PureCookingFatNutrition;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RoundMealIngredientPortionsCommand extends Command
{
    protected $signature = 'menu:round-kitchen-portions
                            {--dry-run : Report changes without writing to the database}';

    protected $description = 'Snap all meal ingredient amounts to kitchen-realistic measures (5 g steps, whole-gram spices)';

    /**
     * Explicit kitchen targets that differ from generic five-gram snapping.
     *
     * @var array<string, array<string, float>>
     */
    private const MEAL_OVERRIDES = [
        'Tamarind Honey & Sesame Chicken w Garlicky Green Beans' => [
            'Bok Choy' => 80.0,
            'Garlicky Green Beans (Base)' => 100.0,
            'Ginger (Raw)' => 5.0,
            'Rice Vinegar' => 10.0,
            'Sesame Oil' => 10.0,
            'Tamarind Paste' => 10.0,
        ],
    ];

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $rows = [];
        $updatedMeals = 0;

        Meal::query()
            ->with('ingredients')
            ->orderBy('name')
            ->each(function (Meal $meal) use ($dryRun, &$rows, &$updatedMeals): void {
                $overrides = self::MEAL_OVERRIDES[$meal->name] ?? [];
                $changes = [];

                foreach ($meal->ingredients as $ingredient) {
                    $current = (float) ($ingredient->pivot->amount_grams ?? 0);

                    // Oils/fats previously snapped to 0 left nameless quantity lines — restore a kitchen pour.
                    if (
                        $current <= 0
                        && (
                            KitchenPortionRounding::isOilIngredient($ingredient)
                            || KitchenPortionRounding::isLiquidFatIngredient($ingredient)
                        )
                    ) {
                        $changes[] = [
                            'ingredient_id' => $ingredient->id,
                            'ingredient' => $ingredient->name,
                            'from' => $current,
                            'to' => 5.0,
                        ];

                        continue;
                    }

                    if ($current <= 0) {
                        continue;
                    }

                    $plausible = PureCookingFatNutrition::applyVolumetricPlausibility($meal, $ingredient, $current);
                    $target = $overrides[$ingredient->name]
                        ?? KitchenPortionRounding::snapGramsForIngredient($ingredient, $plausible);

                    if (abs($current - $target) < 0.001) {
                        continue;
                    }

                    $changes[] = [
                        'ingredient_id' => $ingredient->id,
                        'ingredient' => $ingredient->name,
                        'from' => $current,
                        'to' => $target,
                    ];
                }

                if ($changes === []) {
                    return;
                }

                foreach ($changes as $change) {
                    $rows[] = [
                        $meal->name,
                        $change['ingredient'],
                        $this->formatGrams($change['from']),
                        $this->formatGrams($change['to']),
                    ];
                }

                $updatedMeals++;

                if ($dryRun) {
                    return;
                }

                DB::transaction(function () use ($meal, $changes): void {
                    foreach ($changes as $change) {
                        $grams = round((float) $change['to'], 2);
                        $meal->ingredients()->updateExistingPivot($change['ingredient_id'], [
                            'amount_grams' => $grams,
                            'amount' => $grams,
                            'unit' => 'g',
                        ]);
                    }

                    $meal->load('ingredients');

                    if ($meal->ingredients->isNotEmpty() && ! $meal->is_bulk) {
                        $nutrition = RecipeNutritionCalculator::fromMeal($meal);
                        $meal->update(array_merge(
                            Meal::nutritionSummaryToPersistedAttributes($nutrition),
                            ['nutrition_aggregates_synced' => true],
                        ));
                    }

                    MealRecipeAsIngredientSyncService::syncFromPersistedMeal($meal->fresh(['ingredients']), false);
                });
            });

        if ($rows === []) {
            $this->info('All meal ingredient portions are already kitchen-realistic.');

            return self::SUCCESS;
        }

        $this->table(['Meal', 'Ingredient', 'From', 'To'], $rows);
        $this->info(($dryRun ? 'Would update' : 'Updated')." {$updatedMeals} meal(s), ".count($rows).' ingredient row(s).');

        if (! $dryRun) {
            app(MenuDevelopmentCsvSync::class)->syncMealsFromDatabase();
            app(MealLibraryRefinerSourceSync::class)->syncAllManagedMealsFromDatabase();
        }

        return self::SUCCESS;
    }

    private function formatGrams(float $grams): string
    {
        return rtrim(rtrim(number_format($grams, 4, '.', ''), '0'), '.').'g';
    }
}
