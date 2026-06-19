<?php

namespace App\Console\Commands;

use App\Services\BalancedMealLibraryConfigurator;
use App\Services\MenuDevelopmentCsvExport;
use Illuminate\Console\Command;

class ConfigureBalancedMealLibraryCommand extends Command
{
    protected $signature = 'meals:configure-balanced-library
                            {--export-csv : Write database/data/menu/meals.csv after configuring}';

    protected $description = 'Curate canonical Balanced protocol meals (deck order, tags) and demote non-deck library rows';

    public function handle(BalancedMealLibraryConfigurator $configurator, MenuDevelopmentCsvExport $csvExport): int
    {
        $this->info('Configuring Balanced meal library deck…');

        $result = $configurator->configure();

        $this->table(
            ['Metric', 'Count'],
            [
                ['Canonical deck meals updated', (string) $result['canonical']],
                ['Non-deck meals demoted (sort ≥ '.BalancedMealLibraryConfigurator::NON_CANONICAL_SORT_BASE.')', (string) $result['demoted']],
                ['Bone broth meal created', $result['bone_broth_created'] ? 'yes' : 'no'],
            ],
        );

        $this->line('');
        $this->info('Balanced customer deck (consultation order):');
        foreach (BalancedMealLibraryConfigurator::canonicalSlots() as $slot) {
            $this->line(sprintf('  [%2d] %s (%s)', $slot['sort'], $slot['name'], $slot['slot']));
        }

        if ($this->option('export-csv')) {
            $count = $csvExport->exportMealsToPath(database_path('data/menu/meals.csv'));
            $this->info("Exported {$count} meal row(s) to database/data/menu/meals.csv");
        }

        return self::SUCCESS;
    }
}
