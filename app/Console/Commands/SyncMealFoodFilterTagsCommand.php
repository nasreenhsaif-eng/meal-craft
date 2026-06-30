<?php

namespace App\Console\Commands;

use App\Models\Meal;
use App\Services\MenuDevelopmentCsvSync;
use App\Support\IngredientG6pdSafety;
use App\Support\MealFoodFilterCatalog;
use App\Support\MealFoodFilterResolver;
use Illuminate\Console\Command;

class SyncMealFoodFilterTagsCommand extends Command
{
    protected $signature = 'meals:sync-food-filters
                            {--dry-run : Report changes without writing to the database}
                            {--sync-csv : Export updated meals to database/data/menu/meals.csv after syncing}';

    protected $description = 'Recompute meal food_filter_tags and safety_alert_tags from ingredients (including base-recipe components)';

    public function handle(MenuDevelopmentCsvSync $menuDevelopmentCsvSync): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $updated = 0;
        $rows = [];

        Meal::queryForMealLibrary()
            ->with(['ingredients.components'])
            ->each(function (Meal $meal) use ($dryRun, &$updated, &$rows): void {
                $resolved = MealFoodFilterCatalog::canonicalSlugsFromList(
                    MealFoodFilterResolver::resolveForMeal($meal),
                );
                $current = MealFoodFilterCatalog::canonicalSlugsFromList(
                    is_array($meal->food_filter_tags) ? $meal->food_filter_tags : null,
                );

                $ingredientIds = $meal->ingredients->pluck('id')->map(fn ($id): int => (int) $id)->all();
                $resolvedSafety = IngredientG6pdSafety::mergeTriggerIntoSafetyLabels(
                    MealFoodFilterCatalog::safetyLabelsFromSlugs($resolved),
                    IngredientG6pdSafety::mealContainsG6pdTrigger($ingredientIds),
                );
                $currentSafety = is_array($meal->safety_alert_tags) ? array_values($meal->safety_alert_tags) : [];

                if ($current === $resolved && $currentSafety === $resolvedSafety) {
                    return;
                }

                $rows[] = [
                    $meal->name,
                    $current === [] ? '—' : implode(', ', $current),
                    implode(', ', $resolved),
                ];

                if (! $dryRun) {
                    $meal->update([
                        'food_filter_tags' => $resolved,
                        'safety_alert_tags' => $resolvedSafety,
                    ]);
                }

                $updated++;
            });

        if ($rows === []) {
            $this->info('All meal food filters are already up to date.');

            return self::SUCCESS;
        }

        $this->table(['Meal', 'Before', 'After'], $rows);
        $this->info(($dryRun ? 'Would update' : 'Updated')." {$updated} meal(s).");

        if (! $dryRun && $this->option('sync-csv')) {
            $menuDevelopmentCsvSync->syncMealsFromDatabase();
            $this->info('Synced meals to database/data/menu/meals.csv.');
        }

        return self::SUCCESS;
    }
}
