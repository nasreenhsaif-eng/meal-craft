<?php

namespace App\Console\Commands;

use App\Models\Ingredient;
use App\Services\BaseIngredientService;
use App\Support\MenuDevelopmentCsv;
use App\Support\RecipeComponentsCsvParser;
use Illuminate\Console\Command;
use InvalidArgumentException;

class ConvertBaseRecipeComponentsToNamesCommand extends Command
{
    protected $signature = 'menu:convert-base-recipe-components-to-names
                            {--sync : Upsert converted components into the database after updating the CSV}
                            {--dry-run : Show conversions without writing files}';

    protected $description = 'Rewrite base recipe recipe_components cells from legacy id:amount pairs to name (grams) format';

    public function handle(BaseIngredientService $baseIngredientService): int
    {
        $path = MenuDevelopmentCsv::ingredientsPath();
        $rows = $this->readCsv($path);
        $index = $this->headerIndex($rows[0] ?? []);

        $converted = 0;
        $skipped = 0;
        $errors = [];

        foreach ($rows as $rowIndex => $row) {
            if ($rowIndex === 0 || ! $this->rowIsBaseRecipe($row, $index)) {
                continue;
            }

            $name = trim((string) ($row[$index['name']] ?? ''));
            if ($name === '') {
                continue;
            }

            $componentsCell = trim((string) ($row[$index['recipe_components']] ?? ''));

            if ($componentsCell !== '' && ! $this->cellUsesLegacyIdFormat($componentsCell)) {
                $skipped++;

                continue;
            }

            try {
                $componentRows = $this->resolveComponentRows($name, $componentsCell, $rowIndex + 1);
            } catch (InvalidArgumentException $exception) {
                $errors[] = "{$name}: {$exception->getMessage()}";

                continue;
            }

            if ($componentRows === []) {
                $errors[] = "{$name}: no components to convert.";

                continue;
            }

            $nameCell = RecipeComponentsCsvParser::formatComponentRowsAsNameCell($componentRows);

            if ($nameCell === '') {
                $errors[] = "{$name}: could not format name-based components.";

                continue;
            }

            if ($this->option('dry-run')) {
                $this->line("{$name} => {$nameCell}");

                $converted++;

                continue;
            }

            $row[$index['recipe_components']] = $nameCell;
            $rows[$rowIndex] = $row;
            $converted++;
        }

        if ($this->option('dry-run')) {
            $this->info("Would convert {$converted} base recipe(s); {$skipped} already name-based.");

            foreach ($errors as $error) {
                $this->warn($error);
            }

            return $errors === [] ? self::SUCCESS : self::FAILURE;
        }

        $this->writeCsv($path, $rows);
        $this->info("Converted {$converted} base recipe(s) in {$path}; {$skipped} already name-based.");

        foreach ($errors as $error) {
            $this->warn($error);
        }

        if ($this->option('sync')) {
            return $this->call('menu:restore-base-recipe-components', [
                '--sync-all' => true,
            ]);
        }

        return $errors === [] ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @return list<array{ingredient_id: int, amount_grams: float}>
     */
    private function resolveComponentRows(string $baseName, string $componentsCell, int $csvRowNumber): array
    {
        $fromDatabase = $this->componentRowsFromDatabase($baseName);

        if ($fromDatabase !== []) {
            return $fromDatabase;
        }

        if ($componentsCell === '') {
            return [];
        }

        return RecipeComponentsCsvParser::parseToComponentRows(
            $componentsCell,
            $csvRowNumber,
            $baseName,
        );
    }

    /**
     * @return list<array{ingredient_id: int, amount_grams: float}>
     */
    private function componentRowsFromDatabase(string $baseName): array
    {
        $ingredient = Ingredient::query()
            ->where('name', $baseName)
            ->where('is_verified', true)
            ->with('components')
            ->first();

        if ($ingredient === null || $ingredient->components->isEmpty()) {
            return [];
        }

        $rows = [];

        foreach ($ingredient->components as $child) {
            $grams = (float) ($child->pivot->amount_grams ?? 0);

            if ($grams <= 0) {
                continue;
            }

            $rows[] = [
                'ingredient_id' => (int) $child->getKey(),
                'amount_grams' => $grams,
            ];
        }

        return $rows;
    }

    private function cellUsesLegacyIdFormat(string $cell): bool
    {
        return (bool) preg_match('/(?:^|[|,])\s*\d+\s*:\s*\d/u', $cell);
    }

    /**
     * @param  list<string|null>  $row
     * @param  array<string, int>  $index
     */
    private function rowIsBaseRecipe(array $row, array $index): bool
    {
        $flag = strtolower(trim((string) ($row[$index['is_base_recipe']] ?? '')));

        return in_array($flag, ['1', 'true', 'yes'], true);
    }

    /**
     * @param  list<string|null>  $header
     * @return array<string, int>
     */
    private function headerIndex(array $header): array
    {
        $normalized = [];

        foreach ($header as $position => $label) {
            $key = strtolower(trim(str_replace(' ', '_', (string) $label)));

            if ($key !== '') {
                $normalized[$key] = $position;
            }
        }

        return $normalized;
    }

    /**
     * @return list<list<string|null>>
     */
    private function readCsv(string $path): array
    {
        $handle = fopen($path, 'r');

        if ($handle === false) {
            throw new InvalidArgumentException("Could not read CSV at {$path}.");
        }

        $rows = [];

        while (($row = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
            $rows[] = $row;
        }

        fclose($handle);

        return $rows;
    }

    /**
     * @param  list<list<string|null>>  $rows
     */
    private function writeCsv(string $path, array $rows): void
    {
        $handle = fopen($path, 'w');

        if ($handle === false) {
            throw new InvalidArgumentException("Could not write CSV at {$path}.");
        }

        foreach ($rows as $row) {
            fputcsv($handle, $row, ',', '"', '\\');
        }

        fclose($handle);
    }
}
