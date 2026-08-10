<?php

namespace App\Console\Commands;

use App\Services\CollapsedPrimaryProteinHealer;
use Illuminate\Console\Command;

class HealCollapsedPrimaryProteinCommand extends Command
{
    protected $signature = 'menu:heal-collapsed-protein
                            {--dry-run : List collapsed primary meat/fish portions without writing}';

    protected $description = 'Heal primary beef/chicken/fish portions crushed below 75 g (e.g. 1–2 g sodium-refiner collapse) back to 150 g across the whole meal library';

    public function handle(CollapsedPrimaryProteinHealer $healer): int
    {
        $findings = $healer->audit();

        if ($findings === []) {
            $this->info('No collapsed primary meat/fish portions found.');

            return self::SUCCESS;
        }

        $rows = array_map(
            fn (array $finding): array => [
                $finding['meal'],
                $finding['ingredient'],
                $finding['from'].'g',
                $finding['to'].'g',
            ],
            $findings,
        );

        $this->table(['Meal', 'Ingredient', 'Before', 'After'], $rows);

        if ($this->option('dry-run')) {
            $this->warn('Dry run — '.count($findings).' collapsed portion(s) would be healed.');

            return self::SUCCESS;
        }

        $updated = $healer->heal();
        $this->info('Healed '.count($updated).' meal(s).');

        return self::SUCCESS;
    }
}
