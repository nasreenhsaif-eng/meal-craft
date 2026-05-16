<?php

namespace App;

use App\Models\Ingredient;
use App\Services\BaseIngredientService;
use App\Support\IngredientG6pdSafety;
use App\Support\IngredientLibraryCategory;
use App\Support\RecipeComponentsCsvParser;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class IngredientsImport
{
    public function __construct(private BaseIngredientService $baseIngredientService) {}

    /**
     * Import a CSV with per-100g nutrition columns into the ingredient library.
     *
     * Rules:
     * - Upsert keyed by name (case-sensitive, exact match).
     * - Missing nutrient values default to 0.
     * - All numeric values are cast to float.
     * - Imported rows are always marked verified.
     *
     * CSV headers are case-insensitive; spaces are normalized to underscores.
     *
     * Direct columns:
     * - name
     * - category
     * - fdc_id
     * - calories, protein, carbs, fat (also supports protein_g/carbs_g/fat_g)
     *
     * Dedicated “sickle planning” columns:
     * - b6, b9_folate, b12, iron, magnesium
     *   (also supports vitamin_b6_mg, folate_mcg, vitamin_b12_mcg, iron_mg, magnesium_mg)
     *
     * micronutrients JSON (everything else):
     * - fiber, sugar, calcium, potassium, sodium, zinc
     * - vitamin_a, vitamin_c, vitamin_d, vitamin_e, vitamin_k
     *
     * Physical (optional):
     * - density — g/ml for converting volume units to mass in recipes (default 1.0 when omitted)
     *
     * G6PD safety (optional):
     * - g6pd_trigger — 0 or 1 (also accepts G6PD_Trigger header)
     *
     * Base recipe (optional):
     * - is_base_recipe — 0 or 1
     * - recipe_components — {@code ingredient_id:amount} pairs (comma/pipe-separated) or pipe-separated
     *   {@code Ingredient Name (Weightg)} segments (e.g. {@code Carrots, raw (100g) | Onion (50g)})
     * - finished_weight_grams — optional cooked yield for per-100 g scaling
     */
    public function import(UploadedFile $file): int
    {
        $path = $file->getRealPath();

        if (! is_string($path) || $path === '') {
            return 0;
        }

        $csv = new \SplFileObject($path);
        $csv->setFlags(\SplFileObject::READ_CSV | \SplFileObject::SKIP_EMPTY | \SplFileObject::DROP_NEW_LINE);

        $headers = null;
        $imported = 0;
        $csvRowNumber = 1;
        /** @var list<string> $importErrors */
        $importErrors = [];

        foreach ($csv as $row) {
            if (! is_array($row) || $row === [null] || $row === []) {
                continue;
            }

            if ($headers === null) {
                $headers = collect($row)
                    ->map(fn ($h): string => $this->normalizeHeader((string) $h))
                    ->all();

                continue;
            }

            $csvRowNumber++;

            if (count($row) < count($headers)) {
                $row = array_pad($row, count($headers), null);
            } elseif (count($row) > count($headers)) {
                $row = array_slice($row, 0, count($headers));
            }

            $record = array_combine($headers, $row);

            if (! is_array($record)) {
                continue;
            }

            $name = trim((string) ($record['name'] ?? ''));

            if ($name === '') {
                continue;
            }

            if ($this->recordIsBaseRecipe($record)) {
                try {
                    $this->importBaseRecipeRow($name, $record, $csvRowNumber);
                    $imported++;
                } catch (InvalidArgumentException $e) {
                    $importErrors[] = $e->getMessage();
                }

                continue;
            }

            $attrs = $this->mapRecordToIngredientAttributes($record);

            $this->upsertByNameSafely($name, $attrs);
            $imported++;
        }

        if ($importErrors !== []) {
            throw new InvalidArgumentException(
                __('Ingredient import finished with :count error(s):', ['count' => count($importErrors)])
                ."\n".implode("\n", $importErrors),
            );
        }

        return $imported;
    }

    /**
     * Upsert by name, but never violate the unique `fdc_id` constraint.
     *
     * If the CSV row attempts to set an `fdc_id` that is already used by a *different* ingredient row,
     * we keep the existing record's `fdc_id` (or null) and still overwrite the rest of the nutrition fields.
     *
     * @param  array<string, mixed>  $attrs
     */
    private function upsertByNameSafely(string $name, array $attrs): void
    {
        /** @var Ingredient|null $existingByName */
        $existingByName = Ingredient::query()->where('name', $name)->first();

        $incomingFdcId = $this->toInt($attrs['fdc_id'] ?? null);

        if ($incomingFdcId !== null && $incomingFdcId > 0) {
            /** @var Ingredient|null $existingByFdc */
            $existingByFdc = Ingredient::query()->where('fdc_id', $incomingFdcId)->first();

            if ($existingByFdc !== null && ($existingByName === null || $existingByFdc->id !== $existingByName->id)) {
                // Conflict: this FDC id already belongs to another row. Avoid reassignment + crash.
                if ($existingByName !== null) {
                    $attrs['fdc_id'] = $existingByName->fdc_id;
                } else {
                    unset($attrs['fdc_id']);
                }
            }
        }

        Ingredient::query()->updateOrCreate(['name' => $name], $attrs);
    }

    private function normalizeHeader(string $value): string
    {
        return Str::of($value)
            ->replace("\u{FEFF}", '')
            ->lower()
            ->trim()
            ->replace(' ', '_')
            ->value();
    }

    /**
     * @param  array<string, mixed>  $record
     * @return array<string, mixed>
     */
    private function mapRecordToIngredientAttributes(array $record): array
    {
        $name = trim((string) ($record['name'] ?? ''));

        // Backwards-compat: allow the old template column `micronutrients` as JSON.
        $legacyJsonMicros = [];
        $legacyRaw = $record['micronutrients'] ?? null;
        if (is_string($legacyRaw) && trim($legacyRaw) !== '') {
            $decoded = json_decode($legacyRaw, true);
            if (is_array($decoded)) {
                $legacyJsonMicros = $decoded;
            }
        }

        $calories = $this->floatOrZero($record['calories'] ?? null);
        $protein = $this->floatOrZero($record['protein'] ?? $record['protein_g'] ?? null);
        $carbs = $this->floatOrZero($record['carbs'] ?? $record['carbs_g'] ?? null);
        $fat = $this->floatOrZero($record['fat'] ?? $record['fat_g'] ?? null);

        $b6 = $this->floatOrZero($record['b6'] ?? $record['vitamin_b6_mg'] ?? null);
        $b9 = $this->floatOrZero($record['b9_folate'] ?? $record['folate_mcg'] ?? null);
        $b12 = $this->floatOrZero($record['b12'] ?? $record['vitamin_b12_mcg'] ?? null);
        $iron = $this->floatOrZero($record['iron'] ?? $record['iron_mg'] ?? null);
        $magnesium = $this->floatOrZero($record['magnesium'] ?? $record['magnesium_mg'] ?? null);

        $densityRaw = $this->toFloat($record['density'] ?? null);
        $density = ($densityRaw !== null && $densityRaw > 0) ? $densityRaw : 1.0;

        $micros = [
            'fiber' => $this->floatOrZero($record['fiber'] ?? $record['fiber_g'] ?? $legacyJsonMicros['fiber'] ?? null),
            'sugar' => $this->floatOrZero($record['sugar'] ?? $legacyJsonMicros['sugar'] ?? null),
            'calcium' => $this->floatOrZero($record['calcium'] ?? $record['calcium_mg'] ?? $legacyJsonMicros['calcium'] ?? null),
            'potassium' => $this->floatOrZero($record['potassium'] ?? $record['potassium_mg'] ?? $legacyJsonMicros['potassium'] ?? null),
            'sodium' => $this->floatOrZero($record['sodium'] ?? $record['sodium_mg'] ?? $legacyJsonMicros['sodium'] ?? null),
            'zinc' => $this->floatOrZero($record['zinc'] ?? $record['zinc_mg'] ?? $legacyJsonMicros['zinc'] ?? null),
            'vitamin_c' => $this->floatOrZero($record['vitamin_c'] ?? $record['vitamin_c_mg'] ?? $legacyJsonMicros['vitamin_c'] ?? null),
            'vitamin_a' => $this->floatOrZero($record['vitamin_a'] ?? $record['vitamin_a_rae_mcg'] ?? $legacyJsonMicros['vitamin_a'] ?? null),
            'vitamin_e' => $this->floatOrZero($record['vitamin_e'] ?? $record['vitamin_e_mg'] ?? $legacyJsonMicros['vitamin_e'] ?? null),
            'vitamin_d' => $this->floatOrZero($record['vitamin_d'] ?? $record['vitamin_d_mcg'] ?? $legacyJsonMicros['vitamin_d'] ?? null),
            'vitamin_k' => $this->floatOrZero($record['vitamin_k'] ?? $record['vitamin_k_mcg'] ?? $legacyJsonMicros['vitamin_k'] ?? null),
        ];

        $attrs = [
            'name' => $name,
            'usda_food_category' => $this->toStringOrNull($record['category'] ?? null),
            'fdc_id' => $this->toInt($record['fdc_id'] ?? null),
            'calories' => $calories,
            'protein' => $protein,
            'carbs' => $carbs,
            'fat' => $fat,
            'b6' => $b6,
            'b9_folate' => $b9,
            'b12' => $b12,
            'iron' => $iron,
            'magnesium' => $magnesium,
            'density' => $density,
            'micronutrients' => $micros,
            'is_verified' => true,
            'is_g6pd_trigger' => $this->recordIsTruthy($record['g6pd_trigger'] ?? null),
        ];

        foreach (['description', 'instructions'] as $field) {
            if (array_key_exists($field, $record)) {
                $attrs[$field] = $this->nullableCsvTextFromRecord($record, $field);
            }
        }

        return $attrs;
    }

    /**
     * @param  array<string, mixed>  $record
     */
    private function nullableCsvTextFromRecord(array $record, string $field): ?string
    {
        if (! array_key_exists($field, $record)) {
            return null;
        }

        $raw = $record[$field];
        $s = trim(is_string($raw) ? $raw : (string) $raw);

        return $s !== '' ? $s : null;
    }

    private function recordIsTruthy(mixed $value): bool
    {
        $flag = strtolower(trim((string) $value));

        return in_array($flag, ['1', 'true', 'yes', 'y'], true);
    }

    private function toStringOrNull(mixed $value): ?string
    {
        $s = is_string($value) ? trim($value) : trim((string) $value);

        return $s === '' ? null : $s;
    }

    private function toFloat(mixed $value): ?float
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            $value = trim($value);

            if ($value === '') {
                return null;
            }
        }

        if (! is_numeric($value)) {
            return null;
        }

        return (float) $value;
    }

    private function toInt(mixed $value): ?int
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            $value = trim($value);

            if ($value === '') {
                return null;
            }
        }

        if (! is_numeric($value)) {
            return null;
        }

        return (int) $value;
    }

    private function floatOrZero(mixed $value): float
    {
        return (float) ($this->toFloat($value) ?? 0.0);
    }

    /**
     * @param  array<string, mixed>  $record
     */
    private function recordIsBaseRecipe(array $record): bool
    {
        $flag = strtolower(trim((string) ($record['is_base_recipe'] ?? '')));

        return in_array($flag, ['1', 'true', 'yes', 'y'], true);
    }

    /**
     * @param  array<string, mixed>  $record
     */
    private function importBaseRecipeRow(string $name, array $record, int $csvRowNumber): void
    {
        $componentsCell = trim((string) ($record['recipe_components'] ?? ''));

        if ($componentsCell === '') {
            throw new InvalidArgumentException(__('Base recipe row :name requires recipe_components.', ['name' => $name]));
        }

        $componentRows = RecipeComponentsCsvParser::parseToComponentRows($componentsCell, $csvRowNumber, $name);
        $finished = $this->toFloat($record['finished_weight_grams'] ?? null);

        $existing = Ingredient::query()
            ->where('name', $name)
            ->whereIn('usda_food_category', IngredientLibraryCategory::preparedLabels())
            ->first();

        $libraryText = null;
        foreach (['description', 'instructions'] as $field) {
            if (array_key_exists($field, $record)) {
                $libraryText ??= [];
                $libraryText[$field] = $record[$field];
            }
        }

        $ingredient = $this->baseIngredientService->upsert(
            $existing,
            $name,
            $componentRows,
            $finished !== null && $finished > 0 ? $finished : null,
            $libraryText,
        );

        $explicitG6pd = $this->recordIsTruthy($record['g6pd_trigger'] ?? null);
        $childIds = array_values(array_filter(array_map(
            static fn (array $row): int => (int) ($row['ingredient_id'] ?? 0),
            $componentRows,
        ), static fn (int $id): bool => $id > 0));

        $derivedFromChildren = $childIds !== []
            && Ingredient::query()
                ->whereIn('id', $childIds)
                ->where(function ($q): void {
                    IngredientG6pdSafety::applyEffectiveG6pdConstraintToQuery($q);
                })
                ->exists();

        if ($explicitG6pd || $derivedFromChildren) {
            $ingredient->update(['is_g6pd_trigger' => true]);
        } elseif (array_key_exists('g6pd_trigger', $record)) {
            $ingredient->update(['is_g6pd_trigger' => false]);
        }
    }
}
