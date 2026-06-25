<?php

namespace App\Services;

use App\Support\MenuDevelopmentCsv;

/**
 * Keeps version-controlled menu master CSV files aligned with the live library database.
 *
 * Call after any UI or API change that mutates meals or ingredients so {@see Database\Seeders\MenuDevelopmentSeeder}
 * does not restore stale CSV data over editor changes on the next {@code db:seed}.
 */
final class MenuDevelopmentCsvSync
{
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
        return $this->menuDevelopmentCsvExport->exportMealsToPath(MenuDevelopmentCsv::mealsPath());
    }

    public function syncIngredientsFromDatabase(): int
    {
        return $this->menuDevelopmentCsvExport->exportIngredientsToPath(MenuDevelopmentCsv::ingredientsPath());
    }
}
