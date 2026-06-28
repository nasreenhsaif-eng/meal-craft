<?php

namespace App\Console\Commands;

use App\Services\BalancedDairyFreeManualRecipeAdjustments;
use App\Services\BalancedMicronutrientRecipeRefiner;
use App\Services\MenuDevelopmentCsvSync;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('nutrition:refine-recipes {--skip-csv-sync : Do not write database/data/menu/meals.csv after refining}')]
#[Description('Run isocaloric micronutrient recipe refinement on Balanced rotation meals')]
class NutritionRefineRecipesCommand extends Command
{
    public function handle(
        BalancedMicronutrientRecipeRefiner $refiner,
        BalancedDairyFreeManualRecipeAdjustments $manualAdjustments,
        MenuDevelopmentCsvSync $csvSync,
    ): int {
        $this->info('Running micronutrient recipe refiner on Balanced rotation meals…');

        $updated = $refiner->refine();

        $manual = $manualAdjustments->apply();

        if ($manual !== []) {
            $this->info('Applied manual dairy-free fixture adjustments:');

            foreach ($manual as $name) {
                $this->line("  • {$name}");
            }

            $updated = array_values(array_unique(array_merge($updated, $manual)));
        }

        if ($updated === []) {
            $this->comment('No meals were updated.');
        } else {
            $this->info('Refined '.count($updated).' meal(s):');

            foreach ($updated as $name) {
                $this->line("  • {$name}");
            }
        }

        if (! $this->option('skip-csv-sync')) {
            $count = $csvSync->syncMealsFromDatabase();
            $this->info("Synced {$count} meal row(s) to database/data/menu/meals.csv");
        }

        $this->newLine();
        $this->comment('Run php artisan nutrition:audit-day-coverage --tier=1500 to verify floor coverage.');

        return self::SUCCESS;
    }
}
