<?php

namespace Database\Seeders;

use App\IngredientsImport;
use App\Services\MealCsvLibraryImportService;
use App\Support\MenuDevelopmentCsv;
use Illuminate\Database\Seeder;
use InvalidArgumentException;

/**
 * Seeds the ingredient library and meal library from version-controlled CSV masters.
 *
 * Paste working export rows into:
 * - database/data/menu/ingredients.csv — 30-column production schema (snake_case headers; upserted by {@code name})
 * - database/data/menu/meals.csv — 19-column production schema (snake_case headers; upserted by {@code meal_name})
 *
 * Required meal columns: {@code meal_name}, {@code ingredients_string}. See {@see MenuDevelopmentCsv::MEAL_HEADERS}.
 */
class MenuDevelopmentSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedIngredientsFromCsv();
        $this->seedMealsFromCsv();
    }

    private function seedIngredientsFromCsv(): void
    {
        $path = MenuDevelopmentCsv::ingredientsPath();

        if (! MenuDevelopmentCsv::hasDataRows($path)) {
            $this->command?->warn('Menu seed: skipped ingredients.csv (header only or missing). Paste master rows into database/data/menu/ingredients.csv');

            return;
        }

        try {
            $count = app(IngredientsImport::class)->importFromPath($path, lenientBaseRecipes: true);
        } catch (InvalidArgumentException $exception) {
            $this->command?->error('Menu seed: ingredients.csv import failed.');
            $this->command?->error($exception->getMessage());

            throw $exception;
        }

        $this->command?->info("Menu seed: processed {$count} ingredient CSV row(s) from database/data/menu/ingredients.csv");
    }

    private function seedMealsFromCsv(): void
    {
        $path = MenuDevelopmentCsv::mealsPath();

        if (! MenuDevelopmentCsv::hasDataRows($path)) {
            $this->command?->warn('Menu seed: skipped meals.csv (header only or missing). Paste master rows into database/data/menu/meals.csv');

            return;
        }

        $result = app(MealCsvLibraryImportService::class)->processPath($path);

        $summary = $result['summary'] ?? [];
        $imported = (int) ($summary['imported'] ?? 0);
        $updated = (int) ($summary['updated'] ?? 0);
        $pending = (int) ($summary['pending_ingredient_input'] ?? 0);
        $errors = (int) ($summary['errors'] ?? 0);

        $this->command?->info(sprintf(
            'Menu seed: meal CSV — %d imported, %d updated, %d pending ingredients, %d errors (database/data/menu/meals.csv)',
            $imported,
            $updated,
            $pending,
            $errors,
        ));

        if ($pending > 0) {
            $names = $result['unique_pending_ingredients'] ?? [];
            $preview = is_array($names) ? implode(', ', array_slice($names, 0, 8)) : '';
            $this->command?->warn("Menu seed: import ingredients first or add missing library items. Pending: {$preview}");
        }

        if ($errors > 0) {
            $lines = collect($result['rows'] ?? [])
                ->where('status', 'error')
                ->take(5)
                ->map(fn (array $row): string => (string) ($row['message'] ?? 'Unknown error'))
                ->implode(' | ');

            $this->command?->warn("Menu seed: meal row errors — {$lines}");

            $headerRowError = collect($result['rows'] ?? [])
                ->first(fn (array $row): bool => ($row['line'] ?? null) === 1 && ($row['status'] ?? '') === 'error');

            if ($headerRowError !== null && str_contains((string) ($headerRowError['message'] ?? ''), 'required columns')) {
                throw new InvalidArgumentException(
                    'meals.csv headers do not match the production schema. Required: meal_name, ingredients_string. '
                    .'Expected columns: '.implode(', ', MenuDevelopmentCsv::MEAL_HEADERS),
                );
            }
        }
    }
}
