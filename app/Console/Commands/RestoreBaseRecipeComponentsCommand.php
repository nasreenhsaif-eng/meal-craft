<?php

namespace App\Console\Commands;

use App\IngredientsImport;
use App\Models\Ingredient;
use App\Services\BaseIngredientService;
use App\Services\RecipeNutritionCalculator;
use App\Support\MenuDevelopmentCsv;
use App\Support\RecipeComponentsCsvParser;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Process;
use InvalidArgumentException;

class RestoreBaseRecipeComponentsCommand extends Command
{
    protected $signature = 'menu:restore-base-recipe-components
                            {--commit=6aaa0e5 : Git commit containing the legacy ingredients.csv snapshot}
                            {--import : Re-import restored base recipes into the database after updating the CSV}
                            {--sync-all : Resolve every base recipe row from recipe_components and upsert DB + CSV}
                            {--dry-run : Show what would be restored without writing files}';

    protected $description = 'Restore base-recipe recipe_components from a legacy ingredients.csv git snapshot';

    public function handle(IngredientsImport $ingredientsImport, BaseIngredientService $baseIngredientService): int
    {
        $legacyRows = $this->legacyRows();
        if ($legacyRows === []) {
            $this->error('Could not read legacy ingredients.csv from git.');

            return self::FAILURE;
        }

        $legacyIndex = $this->headerIndex($legacyRows[0]);
        $currentPath = MenuDevelopmentCsv::ingredientsPath();
        $currentRows = $this->readCsv($currentPath);
        $currentIndex = $this->headerIndex($currentRows[0]);

        $ingredientNames = Ingredient::query()
            ->where('is_verified', true)
            ->orderBy('name')
            ->pluck('name')
            ->all();

        $legacyMap = $this->buildLegacyIdMap($legacyRows, $legacyIndex, $ingredientNames);
        $legacyComponentsByName = $this->legacyComponentsByName($legacyRows, $legacyIndex);

        if (! $this->option('dry-run')) {
            $this->writeLegacyMap($legacyMap);
        }

        if ($this->option('sync-all')) {
            return $this->syncAllBaseRecipeComponents(
                $baseIngredientService,
                $currentRows,
                $currentIndex,
                $currentPath,
            );
        }

        $restored = 0;
        $skipped = 0;

        foreach ($currentRows as $rowIndex => $row) {
            if ($rowIndex === 0) {
                continue;
            }

            if (! $this->rowIsBaseRecipe($row, $currentIndex)) {
                continue;
            }

            $name = trim((string) ($row[$currentIndex['name']] ?? ''));
            if ($name === '') {
                continue;
            }

            if (trim((string) ($row[$currentIndex['recipe_components']] ?? '')) !== '') {
                $skipped++;

                continue;
            }

            if (! isset($legacyComponentsByName[$name])) {
                $this->warn("No legacy components found for {$name}");

                continue;
            }

            if ($this->option('dry-run')) {
                $this->line("{$name} => {$legacyComponentsByName[$name]}");
                $restored++;

                continue;
            }

            $row[$currentIndex['recipe_components']] = $legacyComponentsByName[$name];
            $currentRows[$rowIndex] = $row;
            $restored++;
        }

        if ($this->option('dry-run')) {
            $this->info("Would restore {$restored} base recipe component lists ({$skipped} already populated).");
            $this->info('Resolved '.count($legacyMap).' legacy ingredient ids.');

            return self::SUCCESS;
        }

        $this->writeCsv($currentPath, $currentRows);
        $this->info("Updated {$currentPath} ({$restored} restored, {$skipped} skipped).");
        $this->info('Wrote '.count($legacyMap).' legacy ingredient id mappings.');

        if ($this->option('import')) {
            try {
                $count = $ingredientsImport->importFromPath($currentPath, lenientBaseRecipes: false);
            } catch (InvalidArgumentException $exception) {
                $this->error($exception->getMessage());

                return self::FAILURE;
            }

            $this->call('menu:export-csv');
            $this->info("Imported {$count} ingredient CSV row(s) with strict base-recipe components.");
        } else {
            $this->comment('Run with --import to load restored components into the database.');
        }

        return self::SUCCESS;
    }

    /**
     * @param  list<list<string|null>>  $rows
     * @param  array<string, int>  $index
     */
    private function syncAllBaseRecipeComponents(
        BaseIngredientService $service,
        array $rows,
        array $index,
        string $csvPath,
    ): int {
        $synced = 0;
        $errors = [];

        foreach ($rows as $rowIndex => $row) {
            if ($rowIndex === 0 || ! $this->rowIsBaseRecipe($row, $index)) {
                continue;
            }

            $name = trim((string) ($row[$index['name']] ?? ''));
            $componentsCell = trim((string) ($row[$index['recipe_components']] ?? ''));
            if ($name === '' || $componentsCell === '') {
                continue;
            }

            try {
                $componentRows = RecipeComponentsCsvParser::parseToComponentRows(
                    $componentsCell,
                    $rowIndex + 1,
                    $name,
                );
            } catch (InvalidArgumentException $exception) {
                $errors[] = "{$name}: {$exception->getMessage()}";

                continue;
            }

            if ($componentRows === []) {
                continue;
            }

            $ingredient = Ingredient::query()
                ->where('name', $name)
                ->where('is_verified', true)
                ->first();

            if ($ingredient === null || ! $ingredient->isPreparedBaseIngredient()) {
                $errors[] = "{$name}: verified base ingredient row not found in the database.";

                continue;
            }

            $finished = $this->toFloat($row[$index['finished_weight_grams']] ?? null);
            if ($finished <= 0) {
                $finished = array_sum(array_column($componentRows, 'amount_grams'));
            }

            $libraryText = [
                'description' => trim((string) ($row[$index['description']] ?? '')),
                'instructions' => trim((string) ($row[$index['instructions']] ?? '')),
            ];

            try {
                $service->upsert(
                    $ingredient,
                    $name,
                    $componentRows,
                    $finished > 0 ? $finished : null,
                    $libraryText,
                );
            } catch (InvalidArgumentException $exception) {
                $errors[] = "{$name}: {$exception->getMessage()}";

                continue;
            }

            $resolvedCell = collect($componentRows)
                ->map(static fn (array $component): string => sprintf(
                    '%d:%s',
                    (int) $component['ingredient_id'],
                    rtrim(rtrim(number_format((float) $component['amount_grams'], 4, '.', ''), '0'), '.'),
                ))
                ->implode(',');

            $row[$index['recipe_components']] = $resolvedCell;
            $rows[$rowIndex] = $row;
            $synced++;
            $this->line("Synced {$name}");
        }

        if ($errors !== []) {
            foreach ($errors as $error) {
                $this->warn($error);
            }
        }

        $this->writeCsv($csvPath, $rows);
        $this->info("Synced {$synced} base recipe(s) to the database and updated {$csvPath}.");

        if ($errors !== []) {
            $this->error(count($errors).' base recipe(s) could not be synced.');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * @return list<list<string|null>>
     */
    private function legacyRows(): array
    {
        $commit = (string) $this->option('commit');
        $result = Process::run([
            'git',
            'show',
            "{$commit}:database/data/menu/ingredients.csv",
        ]);

        if (! $result->successful()) {
            return [];
        }

        $path = storage_path('app/legacy-ingredients-restore.csv');
        file_put_contents($path, $result->output());
        $rows = $this->readCsv($path);
        @unlink($path);

        return $rows;
    }

    /**
     * @param  list<list<string|null>>  $rows
     * @param  array<string, int>  $legacyIndex
     * @return array<string, string>
     */
    private function legacyComponentsByName(array $rows, array $legacyIndex): array
    {
        $componentsByName = [];

        foreach ($rows as $rowIndex => $row) {
            if ($rowIndex === 0) {
                continue;
            }

            if (! $this->rowIsBaseRecipe($row, $legacyIndex)) {
                continue;
            }

            $name = trim((string) ($row[$legacyIndex['name']] ?? ''));
            $components = trim((string) ($row[$legacyIndex['recipe_components']] ?? ''));
            if ($name !== '' && $components !== '') {
                $componentsByName[$name] = $components;
            }
        }

        return $componentsByName;
    }

    /**
     * @param  list<list<string|null>>  $rows
     * @param  array<string, int>  $legacyIndex
     * @param  list<string>  $ingredientNames
     * @return array<int, string>
     */
    private function buildLegacyIdMap(array $rows, array $legacyIndex, array $ingredientNames): array
    {
        $map = [];

        $recipeRows = [];
        foreach ($rows as $rowIndex => $row) {
            if ($rowIndex === 0 || ! $this->rowIsBaseRecipe($row, $legacyIndex)) {
                continue;
            }

            $componentsCell = trim((string) ($row[$legacyIndex['recipe_components']] ?? ''));
            if (! $this->cellUsesLegacyIdFormat($componentsCell)) {
                continue;
            }

            $recipeRows[] = $row;
        }

        usort(
            $recipeRows,
            fn (array $left, array $right): int => count($this->parseLegacySegments((string) $left[$legacyIndex['recipe_components']]))
                <=> count($this->parseLegacySegments((string) $right[$legacyIndex['recipe_components']])),
        );

        foreach ($recipeRows as $row) {
            $this->tryMapRecipeAssignments($row, $legacyIndex, $ingredientNames, $map, strictOnly: true);
        }

        foreach ($recipeRows as $row) {
            $this->tryMapRecipeAssignments($row, $legacyIndex, $ingredientNames, $map, strictOnly: false);
        }

        $this->resolveSingletonLegacyIds($recipeRows, $legacyIndex, $ingredientNames, $map);

        return $map;
    }

    /**
     * @param  list<list<string|null>>  $recipeRows
     * @param  array<string, int>  $legacyIndex
     * @param  list<string>  $ingredientNames
     * @param  array<int, string>  $map
     */
    private function resolveSingletonLegacyIds(array $recipeRows, array $legacyIndex, array $ingredientNames, array &$map): void
    {
        foreach ($recipeRows as $row) {
            $recipeName = trim((string) ($row[$legacyIndex['name']] ?? ''));
            $componentsCell = trim((string) ($row[$legacyIndex['recipe_components']] ?? ''));
            if ($recipeName === '' || ! $this->cellUsesLegacyIdFormat($componentsCell)) {
                continue;
            }

            $segments = $this->parseLegacySegments($componentsCell);
            $unmapped = array_values(array_filter(
                $segments,
                fn (array $segment): bool => ! isset($map[(int) $segment['legacy_id']]),
            ));

            if (count($unmapped) !== 1) {
                continue;
            }

            $segment = $unmapped[0];
            $legacyId = (int) $segment['legacy_id'];
            $mappedRows = [];
            foreach ($segments as $knownSegment) {
                $knownId = (int) $knownSegment['legacy_id'];
                if (! isset($map[$knownId])) {
                    continue 2;
                }
                $mappedRows[] = ['name' => $map[$knownId], 'grams' => (float) $knownSegment['grams']];
            }

            $targetBatch = $this->targetBatchForRecipe($row, $legacyIndex, $segments);
            $knownBatch = $this->batchNutritionFromRows($mappedRows);
            $residual = [
                'calories' => max(0.0, $targetBatch['calories'] - $knownBatch['calories']),
                'protein' => max(0.0, $targetBatch['protein'] - $knownBatch['protein']),
                'carbs' => max(0.0, $targetBatch['carbs'] - $knownBatch['carbs']),
                'fat' => max(0.0, $targetBatch['fat'] - $knownBatch['fat']),
            ];

            $bestName = null;
            $bestError = INF;
            foreach ($ingredientNames as $candidate) {
                if ($candidate === $recipeName) {
                    continue;
                }
                $trialRows = array_merge($mappedRows, [['name' => $candidate, 'grams' => (float) $segment['grams']]]);
                if (! $this->assignmentMatchesTargetNutrition($row, $legacyIndex, $trialRows)) {
                    continue;
                }
                $error = $this->assignmentNutritionError($row, $legacyIndex, $trialRows);
                if ($error < $bestError) {
                    $bestError = $error;
                    $bestName = $candidate;
                }
            }

            if ($bestName !== null) {
                $map[$legacyId] = $bestName;
            }
        }
    }

    /**
     * @param  list<string|null>  $row
     * @param  array<string, int>  $legacyIndex
     * @param  list<array{legacy_id: int, grams: float}>  $segments
     * @return array{calories: float, protein: float, carbs: float, fat: float}
     */
    private function targetBatchForRecipe(array $row, array $legacyIndex, array $segments): array
    {
        $totalGrams = $this->toFloat($row[$legacyIndex['finished_weight_grams']] ?? null);
        if ($totalGrams <= 0) {
            $totalGrams = array_sum(array_column($segments, 'grams'));
        }
        if ($totalGrams <= 0) {
            $totalGrams = 1.0;
        }

        return [
            'calories' => $this->toFloat($row[$legacyIndex['calories']] ?? null) * $totalGrams / 100,
            'protein' => $this->toFloat($row[$legacyIndex['protein']] ?? null) * $totalGrams / 100,
            'carbs' => $this->toFloat($row[$legacyIndex['carbs']] ?? null) * $totalGrams / 100,
            'fat' => $this->toFloat($row[$legacyIndex['fat']] ?? null) * $totalGrams / 100,
        ];
    }

    /**
     * @param  list<array{name: string, grams: float}>  $rows
     * @return array{calories: float, protein: float, carbs: float, fat: float}
     */
    private function batchNutritionFromRows(array $rows): array
    {
        $calculatorRows = [];
        foreach ($rows as $row) {
            $ingredient = Ingredient::query()->where('name', $row['name'])->where('is_verified', true)->first();
            if ($ingredient === null) {
                return ['calories' => 0.0, 'protein' => 0.0, 'carbs' => 0.0, 'fat' => 0.0];
            }
            $calculatorRows[] = [
                'ingredient_id' => (int) $ingredient->id,
                'amount_grams' => (float) $row['grams'],
            ];
        }

        $nutrition = RecipeNutritionCalculator::fromRows($calculatorRows);

        return [
            'calories' => (float) ($nutrition['calories'] ?? 0),
            'protein' => (float) ($nutrition['protein'] ?? 0),
            'carbs' => (float) ($nutrition['carbs'] ?? 0),
            'fat' => (float) ($nutrition['fat'] ?? 0),
        ];
    }

    /**
     * @param  array<string, int>  $legacyIndex
     * @param  list<string>  $ingredientNames
     * @param  array<int, string>  $map
     */
    private function tryMapRecipeAssignments(
        array $row,
        array $legacyIndex,
        array $ingredientNames,
        array &$map,
        bool $strictOnly,
    ): void {
        $recipeName = trim((string) ($row[$legacyIndex['name']] ?? ''));
        $componentsCell = trim((string) ($row[$legacyIndex['recipe_components']] ?? ''));
        if ($recipeName === '' || ! $this->cellUsesLegacyIdFormat($componentsCell)) {
            return;
        }

        $segments = $this->parseLegacySegments($componentsCell);
        $unmappedSegments = array_values(array_filter(
            $segments,
            fn (array $segment): bool => ! isset($map[(int) $segment['legacy_id']]),
        ));

        if ($unmappedSegments === []) {
            return;
        }

        $context = trim(implode(' ', array_filter([
            (string) ($row[$legacyIndex['description']] ?? ''),
            (string) ($row[$legacyIndex['instructions']] ?? ''),
            $recipeName,
        ])));

        $matches = $strictOnly
            ? $this->strictInstructionMatches($context, $ingredientNames, $recipeName)
            : $this->rankedInstructionMatches($context, $ingredientNames, $recipeName);

        if (count($matches) < count($unmappedSegments)) {
            return;
        }

        $assignment = count($matches) === count($unmappedSegments)
            ? $this->assignmentFromOrderedMatches($unmappedSegments, $matches)
            : $this->bestAssignmentFromCandidates($row, $legacyIndex, $unmappedSegments, $matches);

        if ($assignment === []) {
            return;
        }

        $trialRows = [];
        foreach ($unmappedSegments as $segment) {
            $legacyId = (int) $segment['legacy_id'];
            if (! isset($assignment[$legacyId])) {
                return;
            }
            $trialRows[] = ['name' => $assignment[$legacyId], 'grams' => (float) $segment['grams']];
        }

        if (! $this->assignmentMatchesTargetNutrition($row, $legacyIndex, $trialRows)) {
            return;
        }

        foreach ($assignment as $legacyId => $ingredientName) {
            if ($ingredientName === $recipeName) {
                return;
            }
            if (! isset($map[$legacyId])) {
                $map[$legacyId] = $ingredientName;
            }
        }
    }

    /**
     * @param  list<string>  $ingredientNames
     * @return list<string>
     */
    private function rankedInstructionMatches(string $context, array $ingredientNames, string $recipeName): array
    {
        $contextTokens = $this->tokenize($context);
        $scored = [];

        foreach ($ingredientNames as $ingredientName) {
            if ($ingredientName === $recipeName) {
                continue;
            }

            $nameNormalized = $this->normalize($ingredientName);
            if ($nameNormalized === '') {
                continue;
            }

            $score = 0.0;
            if (str_contains($this->normalize($context), $nameNormalized)) {
                $score += 100.0;
            }

            foreach ($this->tokenize($ingredientName) as $token) {
                if ($contextTokens->contains($token)) {
                    $score += 12.0;
                }
            }

            if ($score > 0) {
                $scored[] = ['name' => $ingredientName, 'score' => $score];
            }
        }

        usort($scored, fn (array $left, array $right): int => $right['score'] <=> $left['score']);

        return array_values(array_unique(array_map(
            fn (array $row): string => $row['name'],
            array_slice($scored, 0, 14),
        )));
    }

    /**
     * @param  list<array{legacy_id: int, grams: float}>  $segments
     * @param  list<string>  $matches
     * @return array<int, string>
     */
    private function assignmentFromOrderedMatches(array $segments, array $matches): array
    {
        usort($segments, fn (array $left, array $right): int => $left['grams'] <=> $right['grams']);
        usort($matches, fn (string $left, string $right): int => $this->typicalGramRank($left) <=> $this->typicalGramRank($right));

        $assignment = [];
        foreach ($segments as $index => $segment) {
            $assignment[(int) $segment['legacy_id']] = $matches[$index];
        }

        return $assignment;
    }

    /**
     * @param  list<string|null>  $row
     * @param  array<string, int>  $legacyIndex
     * @param  list<array{legacy_id: int, grams: float}>  $segments
     * @param  list<string>  $matches
     * @return array<int, string>
     */
    private function bestAssignmentFromCandidates(array $row, array $legacyIndex, array $segments, array $matches): array
    {
        $slotCount = count($segments);
        $bestAssignment = [];
        $bestError = INF;

        $matches = array_slice($matches, 0, max($slotCount + 4, 10));
        $indexes = range(0, count($matches) - 1);
        $combinations = $this->combinations($indexes, $slotCount);

        foreach ($combinations as $combo) {
            $candidateNames = array_map(fn (int $index): string => $matches[$index], $combo);
            $orderedSegments = $segments;
            $orderedNames = $candidateNames;
            usort($orderedSegments, fn (array $left, array $right): int => $left['grams'] <=> $right['grams']);
            usort($orderedNames, fn (string $left, string $right): int => $this->typicalGramRank($left) <=> $this->typicalGramRank($right));

            $trialRows = [];
            $trialAssignment = [];
            foreach ($orderedSegments as $index => $segment) {
                $trialAssignment[(int) $segment['legacy_id']] = $orderedNames[$index];
                $trialRows[] = ['name' => $orderedNames[$index], 'grams' => (float) $segment['grams']];
            }

            if (! $this->assignmentMatchesTargetNutrition($row, $legacyIndex, $trialRows)) {
                continue;
            }

            $error = $this->assignmentNutritionError($row, $legacyIndex, $trialRows);
            if ($error < $bestError) {
                $bestError = $error;
                $bestAssignment = $trialAssignment;
            }
        }

        return $bestAssignment;
    }

    /**
     * @param  list<string|null>  $row
     * @param  array<string, int>  $legacyIndex
     * @param  list<array{name: string, grams: float}>  $rows
     */
    private function assignmentNutritionError(array $row, array $legacyIndex, array $rows): float
    {
        $calculatorRows = [];
        foreach ($rows as $componentRow) {
            $ingredient = Ingredient::query()->where('name', $componentRow['name'])->where('is_verified', true)->first();
            if ($ingredient === null) {
                return INF;
            }
            $calculatorRows[] = [
                'ingredient_id' => (int) $ingredient->id,
                'amount_grams' => (float) $componentRow['grams'],
            ];
        }

        $nutrition = RecipeNutritionCalculator::fromRows($calculatorRows);
        $totalGrams = array_sum(array_column($rows, 'grams'));
        if ($totalGrams <= 0) {
            return INF;
        }

        $targetPer100 = [
            'calories' => $this->toFloat($row[$legacyIndex['calories']] ?? null),
            'protein' => $this->toFloat($row[$legacyIndex['protein']] ?? null),
            'carbs' => $this->toFloat($row[$legacyIndex['carbs']] ?? null),
            'fat' => $this->toFloat($row[$legacyIndex['fat']] ?? null),
        ];

        $error = 0.0;
        foreach (['calories', 'protein', 'carbs', 'fat'] as $key) {
            $target = $targetPer100[$key];
            $actual = ((float) ($nutrition[$key] ?? 0)) * 100 / $totalGrams;
            if ($target <= 0 && $actual <= 0) {
                continue;
            }
            $denominator = max($target, 1.0);
            $error += (($actual - $target) / $denominator) ** 2;
        }

        return $error;
    }

    /**
     * @param  list<int>  $items
     * @return list<list<int>>
     */
    private function combinations(array $items, int $length): array
    {
        if ($length === 0) {
            return [[]];
        }
        if ($length > count($items)) {
            return [];
        }

        $results = [];
        $count = count($items);
        $indexes = range(0, $length - 1);
        $results[] = array_map(fn (int $index): int => $items[$index], $indexes);

        while (true) {
            $i = $length - 1;
            while ($i >= 0 && $indexes[$i] === $i + $count - $length) {
                $i--;
            }
            if ($i < 0) {
                break;
            }
            $indexes[$i]++;
            for ($j = $i + 1; $j < $length; $j++) {
                $indexes[$j] = $indexes[$j - 1] + 1;
            }
            $results[] = array_map(fn (int $index): int => $items[$index], $indexes);
        }

        return $results;
    }

    /**
     * @param  list<string|null>  $row
     * @param  array<string, int>  $legacyIndex
     * @param  list<array{name: string, grams: float}>  $rows
     */
    private function assignmentMatchesTargetNutrition(array $row, array $legacyIndex, array $rows): bool
    {
        $calculatorRows = [];
        foreach ($rows as $componentRow) {
            $ingredient = Ingredient::query()->where('name', $componentRow['name'])->where('is_verified', true)->first();
            if ($ingredient === null) {
                return false;
            }
            $calculatorRows[] = [
                'ingredient_id' => (int) $ingredient->id,
                'amount_grams' => (float) $componentRow['grams'],
            ];
        }

        $nutrition = RecipeNutritionCalculator::fromRows($calculatorRows);
        $totalGrams = array_sum(array_column($rows, 'grams'));
        if ($totalGrams <= 0) {
            return false;
        }

        $targetPer100 = [
            'calories' => $this->toFloat($row[$legacyIndex['calories']] ?? null),
            'protein' => $this->toFloat($row[$legacyIndex['protein']] ?? null),
            'carbs' => $this->toFloat($row[$legacyIndex['carbs']] ?? null),
            'fat' => $this->toFloat($row[$legacyIndex['fat']] ?? null),
        ];

        $actualPer100 = [
            'calories' => ((float) ($nutrition['calories'] ?? 0)) * 100 / $totalGrams,
            'protein' => ((float) ($nutrition['protein'] ?? 0)) * 100 / $totalGrams,
            'carbs' => ((float) ($nutrition['carbs'] ?? 0)) * 100 / $totalGrams,
            'fat' => ((float) ($nutrition['fat'] ?? 0)) * 100 / $totalGrams,
        ];

        foreach (['calories', 'protein', 'carbs', 'fat'] as $key) {
            $target = $targetPer100[$key];
            $actual = $actualPer100[$key];
            if ($target <= 0 && $actual <= 0) {
                continue;
            }
            $denominator = max($target, 1.0);
            if (abs($actual - $target) / $denominator > 0.35) {
                return false;
            }
        }

        return true;
    }

    private function toFloat(mixed $value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }

        return is_numeric($value) ? (float) $value : 0.0;
    }

    /**
     * @param  list<string>  $ingredientNames
     * @return list<string>
     */
    private function strictInstructionMatches(string $context, array $ingredientNames, string $recipeName): array
    {
        $contextTokens = $this->tokenize($context);
        $matches = [];

        foreach ($ingredientNames as $ingredientName) {
            if ($ingredientName === $recipeName) {
                continue;
            }

            $nameNormalized = $this->normalize($ingredientName);
            if ($nameNormalized === '') {
                continue;
            }

            if (str_contains($this->normalize($context), $nameNormalized)) {
                $matches[] = $ingredientName;

                continue;
            }

            $nameTokens = $this->tokenize($ingredientName);
            if ($nameTokens->isNotEmpty() && $nameTokens->every(fn (string $token): bool => $contextTokens->contains($token))) {
                $matches[] = $ingredientName;
            }
        }

        return array_values(array_unique($matches));
    }

    /**
     * @return Collection<int, string>
     */
    private function tokenize(string $value): Collection
    {
        $normalized = $this->normalize($value);
        $tokens = array_values(array_filter(explode(' ', $normalized), fn (string $token): bool => strlen($token) > 2));

        return collect($tokens);
    }

    private function typicalGramRank(string $ingredientName): int
    {
        $normalized = $this->normalize($ingredientName);

        if (str_contains($normalized, 'salt') || str_contains($normalized, 'pepper') || str_contains($normalized, 'spice')) {
            return 1;
        }

        if (str_contains($normalized, 'oil') || str_contains($normalized, 'vinegar') || str_contains($normalized, 'juice')) {
            return 3;
        }

        if (str_contains($normalized, 'water') || str_contains($normalized, 'stock') || str_contains($normalized, 'broth') || str_contains($normalized, 'milk')) {
            return 8;
        }

        return 5;
    }

    /**
     * @param  array<int, string>  $map
     */
    private function writeLegacyMap(array $map): void
    {
        ksort($map);
        $path = database_path('data/menu/legacy_ingredient_id_map.json');
        file_put_contents(
            $path,
            json_encode($map, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)."\n",
        );
    }

    private function cellUsesLegacyIdFormat(string $cell): bool
    {
        return (bool) preg_match('/(?:^|[|,])\s*\d+\s*:\s*\d/u', $cell);
    }

    /**
     * @return list<array{legacy_id: int, grams: float}>
     */
    private function parseLegacySegments(string $cell): array
    {
        $segments = [];
        foreach (preg_split('/[,|]/u', $cell) ?: [] as $segment) {
            $segment = trim($segment);
            if ($segment === '' || ! str_contains($segment, ':')) {
                continue;
            }
            [$idPart, $amountPart] = array_pad(explode(':', $segment, 2), 2, '');
            $idPart = trim($idPart);
            if (! ctype_digit($idPart)) {
                continue;
            }
            if (! preg_match('/^(\d+(?:\.\d+)?)/', trim($amountPart), $matches)) {
                continue;
            }
            $segments[] = [
                'legacy_id' => (int) $idPart,
                'grams' => (float) $matches[1],
            ];
        }

        return $segments;
    }

    /**
     * @param  list<string|null>  $row
     * @param  array<string, int>  $index
     */
    private function rowIsBaseRecipe(array $row, array $index): bool
    {
        $flag = strtolower(trim((string) ($row[$index['is_base_recipe']] ?? '')));

        return in_array($flag, ['1', 'true', 'yes', 'y'], true);
    }

    /**
     * @param  list<string|null>  $header
     * @return array<string, int>
     */
    private function headerIndex(array $header): array
    {
        $index = [];
        foreach ($header as $position => $column) {
            $index[strtolower(trim((string) $column))] = $position;
        }

        return $index;
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
        while (($row = fgetcsv($handle)) !== false) {
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
            throw new InvalidArgumentException("Could not write CSV: {$path}");
        }

        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }

        fclose($handle);
    }

    private function normalize(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9]+/u', ' ', $value) ?? $value;

        return trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
    }
}
