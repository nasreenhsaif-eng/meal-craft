<?php

namespace App\Console\Commands;

use App\Services\IngredientVitaminK2CsvRebuild;
use App\Support\MenuDevelopmentCsv;
use Illuminate\Console\Command;

class RebuildIngredientVitaminK2Command extends Command
{
    protected $signature = 'ingredients:rebuild-vitamin-k2
                            {--path= : CSV path (defaults to menu master ingredients.csv)}
                            {--dry-run : Compute values without writing the CSV}
                            {--sleep=150 : Milliseconds to sleep between USDA API requests}';

    protected $description = 'Rebuild vitamin K2 (menaquinone) per 100 g on the ingredients CSV from USDA FDC + category rules';

    public function handle(IngredientVitaminK2CsvRebuild $rebuild): int
    {
        $path = (string) ($this->option('path') ?: MenuDevelopmentCsv::ingredientsPath());
        $dryRun = (bool) $this->option('dry-run');
        $sleepMs = max(0, (int) $this->option('sleep'));

        $this->info('Rebuilding vitamin K2 on: '.$path);

        if ($dryRun) {
            $this->warn('Dry run — CSV will not be modified.');
        }

        $result = $rebuild->rebuild($path, $dryRun, $sleepMs);

        $this->table(
            ['Metric', 'Count'],
            [
                ['Rows updated', (string) $result['updated']],
                ['USDA FDC payloads used', (string) $result['fetched']],
                ['FDC fetch misses', (string) $result['skipped']],
            ],
        );

        if (! $dryRun) {
            $this->info('Wrote updated vitamin_k2 values to '.$result['path']);
            $this->line('Re-import with: php artisan db:seed --class=Database\\Seeders\\MenuDevelopmentSeeder');
        }

        return self::SUCCESS;
    }
}
