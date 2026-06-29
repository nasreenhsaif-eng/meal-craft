<?php

namespace App\Console\Commands;

use App\Models\Meal;
use App\Services\MealRecipeAsIngredientSyncService;
use App\Services\RecipeNutritionCalculator;
use App\Support\StandardMeatPortion;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class StandardizeMeatPortionsCommand extends Command
{
    protected $signature = 'menu:standardize-meat-portions
                            {--dry-run : Report changes without writing to the database}';

    protected $description = 'Set primary beef, chicken, and fish portions to '.StandardMeatPortion::GRAMS.' g across all meals';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $rows = [];
        $updatedMeals = 0;

        Meal::query()
            ->with('ingredients')
            ->orderBy('name')
            ->each(function (Meal $meal) use ($dryRun, &$rows, &$updatedMeals): void {
                $changes = [];

                foreach ($meal->ingredients as $ingredient) {
                    if (! StandardMeatPortion::isPrimaryMeatIngredient($ingredient->name, $meal->name)) {
                        continue;
                    }

                    $target = StandardMeatPortion::targetPrimaryBeefGrams($meal->ingredients, $meal->name);
                    $current = (float) ($ingredient->pivot->amount_grams ?? 0);

                    if (abs($current - $target) < 0.01) {
                        continue;
                    }

                    $changes[] = [
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
                        $change['from'].'g',
                        $change['to'].'g',
                    ];
                }

                if ($dryRun) {
                    $updatedMeals++;

                    return;
                }

                DB::transaction(function () use ($meal, $changes): void {
                    foreach ($changes as $change) {
                        $ingredient = $meal->ingredients->firstWhere('name', $change['ingredient']);

                        if ($ingredient === null) {
                            continue;
                        }

                        $grams = round((float) $change['to'], 2);
                        $meal->ingredients()->updateExistingPivot($ingredient->id, [
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

                $updatedMeals++;
            });

        if ($rows === []) {
            $this->info('All primary meat portions are already '.StandardMeatPortion::GRAMS.' g.');

            return self::SUCCESS;
        }

        $this->table(['Meal', 'Ingredient', 'Before', 'After'], $rows);
        $this->info(($dryRun ? 'Would update' : 'Updated')." {$updatedMeals} meal(s).");

        return self::SUCCESS;
    }
}
