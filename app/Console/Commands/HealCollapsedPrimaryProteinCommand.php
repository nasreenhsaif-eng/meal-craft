<?php

namespace App\Console\Commands;

use App\Services\CollapsedPrimaryProteinHealer;
use App\Support\StandardMeatPortion;
use Illuminate\Console\Command;

class HealCollapsedPrimaryProteinCommand extends Command
{
    protected $signature = 'menu:heal-collapsed-protein
                            {--dry-run : List collapsed primary meat/fish portions without writing}
                            {--weekly : Also print the 7-day Balanced chicken plate/salad portion report}';

    protected $description = 'Heal primary beef/chicken/fish portions crushed below 75 g (e.g. 1–2 g sodium-refiner collapse) back to 150 g across the whole meal library';

    public function handle(CollapsedPrimaryProteinHealer $healer): int
    {
        $findings = $healer->audit();

        if ($findings === []) {
            $this->info('No collapsed primary meat/fish portions found.');
        } else {
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
            } else {
                $updated = $healer->heal();
                $this->info('Healed '.count($updated).' meal(s).');
            }
        }

        if ($this->option('weekly') || $findings !== []) {
            $this->printWeeklyChickenReport($healer);
        }

        $weeklyProblems = array_values(array_filter(
            $healer->auditWeeklyChickenSlots(),
            fn (array $row): bool => ! $row['ok'],
        ));

        if ($weeklyProblems !== []) {
            $this->error(count($weeklyProblems).' Balanced weekly chicken slot(s) still unhealthy (collapsed or missing).');

            return self::FAILURE;
        }

        if ($this->option('weekly')) {
            $this->info('All 7 days: chicken plate + chicken salad portions are at least '.(StandardMeatPortion::GRAMS * CollapsedPrimaryProteinHealer::COLLAPSED_FRACTION).' g (target '.StandardMeatPortion::GRAMS.' g).');
        }

        return self::SUCCESS;
    }

    private function printWeeklyChickenReport(CollapsedPrimaryProteinHealer $healer): void
    {
        $this->line('');
        $this->info('Balanced weekly chicken slots (Main 1 plate · Main 2 salad):');

        $rows = array_map(
            function (array $row): array {
                $status = match ($row['issue']) {
                    null => 'ok',
                    'collapsed' => 'COLLAPSED '.$row['grams'].'g',
                    'missing_chicken' => 'MISSING chicken',
                    'missing_meal' => 'MISSING meal',
                    default => (string) $row['issue'],
                };

                return [
                    (string) $row['day'],
                    $row['slot'],
                    $row['meal'],
                    $row['ingredient'] ?? '—',
                    $row['grams'] !== null ? $row['grams'].'g' : '—',
                    $status,
                ];
            },
            $healer->auditWeeklyChickenSlots(),
        );

        $this->table(['Day', 'Slot', 'Meal', 'Chicken', 'Grams', 'Status'], $rows);
    }
}
