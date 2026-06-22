<?php

namespace App\Console\Commands;

use App\Models\Meal;
use App\Support\MealDietTagResolver;
use Illuminate\Console\Command;

class SyncMealDietTagsCommand extends Command
{
    protected $signature = 'meals:sync-diet-tags
                            {--dry-run : Report changes without writing to the database}';

    protected $description = 'Recompute meal diet_tags from ingredients (including base-recipe components)';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $updated = 0;
        $rows = [];

        Meal::query()
            ->with(['ingredients.components'])
            ->orderBy('name')
            ->each(function (Meal $meal) use ($dryRun, &$updated, &$rows): void {
                $resolved = MealDietTagResolver::resolveForMeal($meal);
                $current = is_array($meal->diet_tags) ? array_values($meal->diet_tags) : [];

                if ($current === $resolved) {
                    return;
                }

                $rows[] = [
                    $meal->name,
                    $current === [] ? '—' : implode(', ', $current),
                    implode(', ', $resolved),
                ];

                if (! $dryRun) {
                    $meal->update(['diet_tags' => $resolved]);
                }

                $updated++;
            });

        if ($rows === []) {
            $this->info('All meal diet tags are already up to date.');

            return self::SUCCESS;
        }

        $this->table(['Meal', 'Before', 'After'], $rows);
        $this->info(($dryRun ? 'Would update' : 'Updated')." {$updated} meal(s).");

        return self::SUCCESS;
    }
}
