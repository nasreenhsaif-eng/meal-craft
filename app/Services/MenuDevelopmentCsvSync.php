<?php

namespace App\Services;

use App\Models\Meal;
use App\Support\MenuDevelopmentCsv;
use Illuminate\Support\Facades\File;
use RuntimeException;

/**
 * Keeps version-controlled menu master CSV files aligned with the live library database.
 *
 * Call after any UI or API change that mutates meals or ingredients so {@see Database\Seeders\MenuDevelopmentSeeder}
 * does not restore stale CSV data over editor changes on the next {@code db:seed}.
 */
final class MenuDevelopmentCsvSync
{
    /**
     * Refuse to overwrite a fuller master CSV with a sparse DB export (RefreshDatabase fixtures,
     * partial local DBs). Half the on-disk meal count is the floor.
     */
    private const MIN_DB_MEALS_FRACTION_OF_CSV = 0.5;

    public function __construct(
        private MenuDevelopmentCsvExport $menuDevelopmentCsvExport,
    ) {}

    /**
     * @return array{meals: int, ingredients: int}
     */
    public function syncAllFromDatabase(): array
    {
        return [
            'ingredients' => $this->syncIngredientsFromDatabase(),
            'meals' => $this->syncMealsFromDatabase(),
        ];
    }

    public function syncMealsFromDatabase(): int
    {
        $path = MenuDevelopmentCsv::mealsPath();
        $this->assertDatabaseIsNotSparseRelativeToMealsCsv($path);

        return $this->menuDevelopmentCsvExport->exportMealsToPath($path);
    }

    public function syncIngredientsFromDatabase(): int
    {
        return $this->menuDevelopmentCsvExport->exportIngredientsToPath(MenuDevelopmentCsv::ingredientsPath());
    }

    private function assertDatabaseIsNotSparseRelativeToMealsCsv(string $path): void
    {
        if (! File::exists($path)) {
            return;
        }

        $csvMealCount = $this->countMealDataRowsInCsv($path);

        if ($csvMealCount <= 0) {
            return;
        }

        $dbMealCount = Meal::queryForMealLibrary()->count();
        $minimumDbMeals = (int) floor($csvMealCount * self::MIN_DB_MEALS_FRACTION_OF_CSV);

        if ($dbMealCount >= $minimumDbMeals) {
            return;
        }

        throw new RuntimeException(
            "Refusing to sync meals.csv: library has {$dbMealCount} meal(s) but {$path} has {$csvMealCount} row(s). ".
            "A sparse database export would wipe the master CSV (need at least {$minimumDbMeals})."
        );
    }

    private function countMealDataRowsInCsv(string $path): int
    {
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            return 0;
        }

        $headers = fgetcsv($handle);

        if ($headers === false) {
            fclose($handle);

            return 0;
        }

        $count = 0;

        while (($row = fgetcsv($handle)) !== false) {
            if ($row === [null] || $row === false) {
                continue;
            }

            if (count(array_filter($row, fn ($cell): bool => trim((string) $cell) !== '')) === 0) {
                continue;
            }

            $count++;
        }

        fclose($handle);

        return $count;
    }
}
