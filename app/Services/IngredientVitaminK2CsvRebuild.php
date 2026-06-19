<?php

namespace App\Services;

use App\Models\Ingredient;
use App\Support\RecipeComponentsCsvParser;
use App\Support\VitaminK2Resolver;
use InvalidArgumentException;

/**
 * Rebuilds vitamin K2 (menaquinone) per 100 g on the master ingredients CSV from USDA FDC + category rules.
 */
final class IngredientVitaminK2CsvRebuild
{
    public function __construct(
        private UsdaFoodDetailClient $fdcClient,
    ) {}

    /**
     * @return array{updated: int, fetched: int, skipped: int, path: string}
     */
    public function rebuild(string $path, bool $dryRun = false, int $sleepMs = 150): array
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw new InvalidArgumentException(__('Ingredients CSV not readable: :path', ['path' => $path]));
        }

        $rows = $this->readCsv($path);
        if ($rows === []) {
            throw new InvalidArgumentException(__('Ingredients CSV is empty.'));
        }

        $header = $rows[0];
        $header = $this->normalizeHeaderRow($header);
        $rows[0] = $header;

        $columnIndex = $this->columnIndexMap($header);
        $nameIdx = $columnIndex['name'] ?? null;
        $categoryIdx = $columnIndex['category'] ?? null;
        $fdcIdx = $columnIndex['fdc_id'] ?? null;
        $k2Idx = $columnIndex['vitamin_k2'] ?? null;
        $baseIdx = $columnIndex['is_base_recipe'] ?? null;
        $componentsIdx = $columnIndex['recipe_components'] ?? null;
        $finishedIdx = $columnIndex['finished_weight_grams'] ?? null;

        if ($nameIdx === null || $categoryIdx === null || $k2Idx === null) {
            throw new InvalidArgumentException(__('Ingredients CSV missing required columns (name, category, vitamin_k2).'));
        }

        $updated = 0;
        $fetched = 0;
        $skipped = 0;

        /** @var array<string, float> $k2ByName */
        $k2ByName = [];

        for ($i = 1, $count = count($rows); $i < $count; $i++) {
            $row = $rows[$i];
            $row = $this->padRow($row, count($header));

            $name = trim((string) ($row[$nameIdx] ?? ''));
            if ($name === '') {
                continue;
            }

            if ($this->rowIsBaseRecipe($row, $baseIdx)) {
                continue;
            }

            $category = trim((string) ($row[$categoryIdx] ?? ''));
            $fdcId = (int) ($row[$fdcIdx] ?? 0);

            $nutrientMap = [];
            if ($fdcId > 0) {
                $nutrientMap = $this->fdcClient->nutrientMapForFdcId($fdcId);
                if ($nutrientMap !== []) {
                    $fetched++;
                } else {
                    $skipped++;
                }

                if ($sleepMs > 0) {
                    usleep($sleepMs * 1000);
                }
            }

            $k2 = VitaminK2Resolver::resolve($name, $category, $nutrientMap);
            $row[$k2Idx] = $this->formatDecimal($k2);
            $rows[$i] = $row;
            $k2ByName[strtolower($name)] = $k2;
            $updated++;
        }

        for ($i = 1, $count = count($rows); $i < $count; $i++) {
            $row = $rows[$i];
            $row = $this->padRow($row, count($header));

            if (! $this->rowIsBaseRecipe($row, $baseIdx)) {
                continue;
            }

            $name = trim((string) ($row[$nameIdx] ?? ''));
            $componentsCell = trim((string) ($row[$componentsIdx] ?? ''));
            $finishedGrams = (float) ($row[$finishedIdx] ?? 0);

            if ($componentsCell === '') {
                continue;
            }

            $batchK2 = $this->sumComponentVitaminK2($componentsCell, $name, $k2ByName);

            if ($finishedGrams <= 0) {
                $finishedGrams = $this->sumComponentGrams($componentsCell, $name);
            }

            if ($finishedGrams <= 0) {
                continue;
            }

            $per100 = round($batchK2 * (100.0 / $finishedGrams), 2);
            $row[$k2Idx] = $this->formatDecimal($per100);
            $rows[$i] = $row;
            $k2ByName[strtolower($name)] = $per100;
            $updated++;
        }

        if (! $dryRun) {
            $this->writeCsv($path, $rows);
        }

        return [
            'updated' => $updated,
            'fetched' => $fetched,
            'skipped' => $skipped,
            'path' => $path,
        ];
    }

    /**
     * @param  array<string, float>  $k2ByName
     */
    private function sumComponentVitaminK2(string $componentsCell, string $baseRecipeName, array $k2ByName): float
    {
        try {
            $componentRows = RecipeComponentsCsvParser::parseToComponentRows($componentsCell, null, $baseRecipeName);
        } catch (InvalidArgumentException) {
            return 0.0;
        }

        $total = 0.0;

        foreach ($componentRows as $componentRow) {
            $ingredientId = (int) ($componentRow['ingredient_id'] ?? 0);
            $grams = (float) ($componentRow['amount_grams'] ?? 0);

            if ($ingredientId <= 0 || $grams <= 0) {
                continue;
            }

            $ingredient = Ingredient::query()->find($ingredientId);
            $componentName = $ingredient?->name;
            $k2Per100 = 0.0;

            if ($componentName !== null) {
                $k2Per100 = $k2ByName[strtolower($componentName)] ?? $this->k2FromIngredientModel($ingredient);
            }

            $total += $k2Per100 * ($grams / 100.0);
        }

        return $total;
    }

    private function sumComponentGrams(string $componentsCell, string $baseRecipeName): float
    {
        try {
            $componentRows = RecipeComponentsCsvParser::parseToComponentRows($componentsCell, null, $baseRecipeName);
        } catch (InvalidArgumentException) {
            return 0.0;
        }

        $total = 0.0;

        foreach ($componentRows as $componentRow) {
            $total += (float) ($componentRow['amount_grams'] ?? 0);
        }

        return $total;
    }

    private function k2FromIngredientModel(?Ingredient $ingredient): float
    {
        if ($ingredient === null) {
            return 0.0;
        }

        $micros = is_array($ingredient->micronutrients) ? $ingredient->micronutrients : [];

        return (float) ($micros['vitamin_k2'] ?? $micros['vitamin_k'] ?? 0);
    }

    /**
     * @param  list<string|null>  $header
     * @return array<string, int>
     */
    private function columnIndexMap(array $header): array
    {
        $map = [];

        foreach ($header as $index => $label) {
            $normalized = $this->normalizeHeader((string) $label);
            $map[$normalized] = $index;
        }

        return $map;
    }

    /**
     * @param  list<string|null>  $header
     * @return list<string|null>
     */
    private function normalizeHeaderRow(array $header): array
    {
        $normalized = [];

        foreach ($header as $label) {
            $normalized[] = $this->normalizeHeader((string) $label);
        }

        if (! in_array('vitamin_k2', $normalized, true)) {
            $replaced = false;

            foreach ($normalized as $index => $label) {
                if ($label === 'vitamin_k') {
                    $normalized[$index] = 'vitamin_k2';
                    $replaced = true;
                }
            }

            if (! $replaced) {
                $normalized[] = 'vitamin_k2';
            }
        }

        return $normalized;
    }

    private function normalizeHeader(string $value): string
    {
        $value = str_replace("\u{FEFF}", '', trim($value));

        return strtolower(str_replace(' ', '_', $value));
    }

    /**
     * @param  list<string|null>  $row
     */
    private function rowIsBaseRecipe(array $row, ?int $baseIdx): bool
    {
        if ($baseIdx === null) {
            return false;
        }

        $flag = strtolower(trim((string) ($row[$baseIdx] ?? '')));

        return in_array($flag, ['1', 'true', 'yes', 'y'], true);
    }

    /**
     * @return list<list<string|null>>
     */
    private function readCsv(string $path): array
    {
        $handle = fopen($path, 'r');

        if ($handle === false) {
            return [];
        }

        $rows = [];

        try {
            while (($row = fgetcsv($handle)) !== false) {
                if ($row === [null] || $row === false) {
                    continue;
                }

                $rows[] = $row;
            }
        } finally {
            fclose($handle);
        }

        return $rows;
    }

    /**
     * @param  list<list<string|null>>  $rows
     */
    private function writeCsv(string $path, array $rows): void
    {
        $tempPath = $path.'.tmp';

        $handle = fopen($tempPath, 'w');

        if ($handle === false) {
            throw new InvalidArgumentException(__('Unable to write ingredients CSV.'));
        }

        try {
            foreach ($rows as $row) {
                if (fputcsv($handle, $row) === false) {
                    throw new InvalidArgumentException(__('Unable to write ingredients CSV row.'));
                }
            }
        } finally {
            fclose($handle);
        }

        if (! rename($tempPath, $path)) {
            @unlink($tempPath);

            throw new InvalidArgumentException(__('Unable to replace ingredients CSV.'));
        }
    }

    /**
     * @param  list<string|null>  $row
     * @return list<string|null>
     */
    private function padRow(array $row, int $width): array
    {
        if (count($row) < $width) {
            return array_pad($row, $width, '');
        }

        if (count($row) > $width) {
            return array_slice($row, 0, $width);
        }

        return $row;
    }

    private function formatDecimal(float $value): string
    {
        if ($value <= 0) {
            return '0';
        }

        $formatted = number_format($value, 2, '.', '');
        $formatted = rtrim(rtrim($formatted, '0'), '.');

        return $formatted === '' ? '0' : $formatted;
    }
}
