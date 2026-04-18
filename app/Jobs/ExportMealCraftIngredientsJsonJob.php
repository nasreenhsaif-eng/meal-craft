<?php

namespace App\Jobs;

use App\Services\MealCraftIngredientsJsonExporter;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use JsonException;
use Throwable;

class ExportMealCraftIngredientsJsonJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    /**
     * Full-library JSON export can be slow on large tables.
     */
    public int $timeout = 300;

    public function handle(): void
    {
        if (function_exists('set_time_limit')) {
            set_time_limit(0);
        }

        try {
            MealCraftIngredientsJsonExporter::export();
        } catch (JsonException|Throwable $e) {
            report($e);
        }
    }
}
