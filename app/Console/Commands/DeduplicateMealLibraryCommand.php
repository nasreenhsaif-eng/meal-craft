<?php

namespace App\Console\Commands;

use App\Services\MealLibraryDedupeService;
use Illuminate\Console\Command;

class DeduplicateMealLibraryCommand extends Command
{
    protected $signature = 'meals:deduplicate-library {--dry-run : List changes without modifying the database}';

    protected $description = 'Remove duplicate meals with the same normalized name, keeping the most recently updated row';

    public function handle(MealLibraryDedupeService $mealLibraryDedupeService): int
    {
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->info(__('Running in dry-run mode; no rows will be deleted.'));
        }

        $result = $mealLibraryDedupeService->deduplicate($dryRun);

        $this->info(__('Duplicate name groups resolved: :n', ['n' => $result['groups_resolved']]));
        $this->info(__('Meals removed: :n', ['n' => $result['meals_removed']]));

        return self::SUCCESS;
    }
}
