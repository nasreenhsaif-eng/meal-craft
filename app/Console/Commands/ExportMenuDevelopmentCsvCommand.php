<?php

namespace App\Console\Commands;

use App\Services\MenuDevelopmentCsvExport;
use App\Support\MenuDevelopmentCsv;
use Illuminate\Console\Command;

class ExportMenuDevelopmentCsvCommand extends Command
{
    protected $signature = 'menu:export-csv';

    protected $description = 'Export live meal and ingredient library rows into database/data/menu/*.csv master files';

    public function handle(MenuDevelopmentCsvExport $menuDevelopmentCsvExport): int
    {
        $counts = $menuDevelopmentCsvExport->exportToDefaultPaths();

        $this->info(sprintf(
            'Exported %d ingredient row(s) to %s',
            $counts['ingredients'],
            MenuDevelopmentCsv::INGREDIENTS_RELATIVE_PATH,
        ));
        $this->info(sprintf(
            'Exported %d meal row(s) to %s',
            $counts['meals'],
            MenuDevelopmentCsv::MEALS_RELATIVE_PATH,
        ));

        return self::SUCCESS;
    }
}
