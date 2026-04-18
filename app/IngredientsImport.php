<?php

namespace App;

use App\Models\Ingredient;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

final class IngredientsImport
{
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

            $attrs = $this->mapRecordToIngredientAttributes($record);

            $this->upsertByNameSafely($name, $attrs);
            $imported++;
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

        return [
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
        ];
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
}
