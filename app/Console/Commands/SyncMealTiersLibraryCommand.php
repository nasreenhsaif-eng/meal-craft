<?php

namespace App\Console\Commands;

use App\Services\MealTiersLibraryCopyService;
use Illuminate\Console\Command;

class SyncMealTiersLibraryCommand extends Command
{
    protected $signature = 'meals:sync-meal-tiers-library
                            {--resync-only : Only refresh calorie tabs on existing tiers meals}';

    protected $description = 'Copy all Meal Library meals into the Meal Tiers Library and refresh calorie tabs';

    public function handle(MealTiersLibraryCopyService $copyService): int
    {
        if (! $this->option('resync-only')) {
            $result = $copyService->copyAllMissingFromClassic();
            $this->info("Copied {$result['copied']} meals; skipped {$result['skipped']} already present.");
        }

        $resynced = $copyService->resyncAllCalorieTiers();
        $this->info("Resynced calorie tabs for {$resynced} Meal Tiers Library meals.");

        return self::SUCCESS;
    }
}
