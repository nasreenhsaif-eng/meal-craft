<?php

namespace App\Services;

use App\Models\Ingredient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use JsonException;

/**
 * Meal Craft Analysis: resolve verified nutrition from the library (database, then enriched JSON export) before any external APIs.
 */
final class LocalVerifiedIngredientLookup
{
    /**
     * @return array{
     *     ingredient: Ingredient,
     *     quantity_g: float,
     *     quantity_source: 'parsed_input'|'library_portion'|'default_100g',
     *     source: 'database'|'json_export'
     * }|null
     */
    public function resolve(string $originalInput): ?array
    {
        $originalInput = trim($originalInput);

        if ($originalInput === '') {
            return null;
        }

        foreach ($this->needleCandidates($originalInput) as $needle) {
            if ($needle === '') {
                continue;
            }

            $row = $this->findVerifiedInDatabase($needle);

            if ($row !== null) {
                $qty = $this->estimateGramsFromPhrase($originalInput, $row);

                return [
                    'ingredient' => $row,
                    'quantity_g' => $qty['quantity_g'],
                    'quantity_source' => $qty['quantity_source'],
                    'source' => 'database',
                ];
            }
        }

        foreach ($this->needleCandidates($originalInput) as $needle) {
            if ($needle === '') {
                continue;
            }

            $snapshot = $this->findVerifiedInJsonExport($needle);

            if ($snapshot !== null) {
                $model = Ingredient::make($snapshot);
                $model->exists = false;
                $qty = $this->estimateGramsFromPhrase($originalInput, $model);

                return [
                    'ingredient' => $model,
                    'quantity_g' => $qty['quantity_g'],
                    'quantity_source' => $qty['quantity_source'],
                    'source' => 'json_export',
                ];
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function needleCandidates(string $input): array
    {
        $collapsed = trim((string) preg_replace('/\s+/u', ' ', $input));

        if ($collapsed === '') {
            return [];
        }

        $withoutLeadingMeasure = (string) preg_replace(
            '/^\d+(?:\.\d+)?\s*(?:g|gram|grams|kg|kilograms?|oz|ounce|ounces|lb|lbs|pounds?|ml|milliliters?|l|liters?)\b\.?\s*/iu',
            '',
            $collapsed
        );

        $withoutArticlePhrase = (string) preg_replace(
            '/^(?:a|an)\s+(?:handful|cup|cups|tbsp|tablespoon|tablespoons|tsp|teaspoon|teaspoons|dash|pinch)\s+of\s+/iu',
            '',
            $withoutLeadingMeasure !== $collapsed ? $withoutLeadingMeasure : $collapsed
        );

        $out = [];

        foreach ([$collapsed, $withoutLeadingMeasure, $withoutArticlePhrase, trim($withoutLeadingMeasure), trim($withoutArticlePhrase)] as $chunk) {
            $n = mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $chunk)));

            if ($n !== '' && ! in_array($n, $out, true)) {
                $out[] = $n;
            }
        }

        return $out;
    }

    private function findVerifiedInDatabase(string $needle): ?Ingredient
    {
        $exact = Ingredient::query()
            ->where('is_verified', true)
            ->whereNotNull('fdc_id')
            ->where('calories', '>', 0)
            ->where(function ($q) use ($needle): void {
                $q->whereRaw('LOWER(TRIM(name)) = ?', [$needle])
                    ->orWhereRaw('LOWER(TRIM(COALESCE(standardized_name, ""))) = ?', [$needle]);
            })
            ->orderBy('id')
            ->first();

        if ($exact !== null) {
            return $exact;
        }

        if (mb_strlen($needle) >= 4) {
            $prefix = Ingredient::query()
                ->where('is_verified', true)
                ->whereNotNull('fdc_id')
                ->where('calories', '>', 0)
                ->where(function ($q) use ($needle): void {
                    $q->whereRaw('LOWER(TRIM(name)) LIKE ?', [$needle.'%'])
                        ->orWhereRaw('LOWER(TRIM(COALESCE(standardized_name, ""))) LIKE ?', [$needle.'%']);
                })
                ->orderByRaw('LENGTH(TRIM(name)) ASC')
                ->orderBy('id')
                ->first();

            if ($prefix !== null) {
                return $prefix;
            }
        }

        if (mb_strlen($needle) >= 6) {
            return Ingredient::query()
                ->where('is_verified', true)
                ->whereNotNull('fdc_id')
                ->where('calories', '>', 0)
                ->where(function ($q) use ($needle): void {
                    $like = '%'.$needle.'%';
                    $q->whereRaw('LOWER(TRIM(name)) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(TRIM(COALESCE(standardized_name, ""))) LIKE ?', [$like]);
                })
                ->orderByRaw('LENGTH(TRIM(name)) ASC')
                ->orderBy('id')
                ->first();
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findVerifiedInJsonExport(string $needle): ?array
    {
        $path = MealCraftIngredientsJsonExporter::defaultPath();

        if (! File::isFile($path)) {
            return null;
        }

        try {
            $mtime = (int) File::lastModified($path);
            /** @var list<array<string, mixed>> $rows */
            $rows = Cache::remember(
                'meal_craft_enriched_ingredients_json:'.$mtime,
                300,
                function () use ($path): array {
                    $raw = File::get($path);
                    $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);

                    return is_array($decoded) ? $decoded : [];
                }
            );
        } catch (JsonException|\Throwable) {
            return null;
        }

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            if (! filter_var($row['is_verified'] ?? false, FILTER_VALIDATE_BOOL)) {
                continue;
            }

            if (! isset($row['fdc_id']) || (int) $row['fdc_id'] <= 0) {
                continue;
            }

            if (! isset($row['calories']) || (float) $row['calories'] <= 0.0) {
                continue;
            }

            $name = isset($row['name']) && is_string($row['name']) ? mb_strtolower(trim($row['name'])) : '';
            $std = isset($row['standardized_name']) && is_string($row['standardized_name'])
                ? mb_strtolower(trim($row['standardized_name']))
                : '';

            if ($name === $needle || ($std !== '' && $std === $needle)) {
                return $this->jsonRowToIngredientAttributes($row);
            }
        }

        if (mb_strlen($needle) >= 4) {
            foreach ($rows as $row) {
                if (! is_array($row)) {
                    continue;
                }

                if (! filter_var($row['is_verified'] ?? false, FILTER_VALIDATE_BOOL)) {
                    continue;
                }

                if (! isset($row['fdc_id']) || (int) $row['fdc_id'] <= 0) {
                    continue;
                }

                if (! isset($row['calories']) || (float) $row['calories'] <= 0.0) {
                    continue;
                }

                $name = isset($row['name']) && is_string($row['name']) ? mb_strtolower(trim($row['name'])) : '';
                $std = isset($row['standardized_name']) && is_string($row['standardized_name'])
                    ? mb_strtolower(trim($row['standardized_name']))
                    : '';

                if (($name !== '' && str_starts_with($name, $needle))
                    || ($std !== '' && str_starts_with($std, $needle))) {
                    return $this->jsonRowToIngredientAttributes($row);
                }
            }
        }

        if (mb_strlen($needle) >= 6) {
            foreach ($rows as $row) {
                if (! is_array($row)) {
                    continue;
                }

                if (! filter_var($row['is_verified'] ?? false, FILTER_VALIDATE_BOOL)) {
                    continue;
                }

                if (! isset($row['fdc_id']) || (int) $row['fdc_id'] <= 0) {
                    continue;
                }

                if (! isset($row['calories']) || (float) $row['calories'] <= 0.0) {
                    continue;
                }

                $name = isset($row['name']) && is_string($row['name']) ? mb_strtolower(trim($row['name'])) : '';
                $std = isset($row['standardized_name']) && is_string($row['standardized_name'])
                    ? mb_strtolower(trim($row['standardized_name']))
                    : '';

                if (($name !== '' && str_contains($name, $needle))
                    || ($std !== '' && str_contains($std, $needle))) {
                    return $this->jsonRowToIngredientAttributes($row);
                }
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function jsonRowToIngredientAttributes(array $row): array
    {
        $micro = $row['micronutrients'] ?? [];
        $microArr = is_array($micro) ? $micro : [];

        return [
            'id' => isset($row['id']) ? (int) $row['id'] : null,
            'name' => (string) ($row['name'] ?? ''),
            'standardized_name' => isset($row['standardized_name']) ? (string) $row['standardized_name'] : null,
            'portion_grams' => isset($row['portion_grams']) ? (float) $row['portion_grams'] : null,
            'fdc_id' => isset($row['fdc_id']) ? (int) $row['fdc_id'] : null,
            'usda_description' => isset($row['usda_description']) ? (string) $row['usda_description'] : null,
            'usda_data_type' => isset($row['usda_data_type']) ? (string) $row['usda_data_type'] : null,
            'usda_food_category' => isset($row['usda_food_category']) ? (string) $row['usda_food_category'] : null,
            'calories' => (float) ($row['calories'] ?? 0),
            'protein' => (float) ($row['protein'] ?? 0),
            'carbs' => (float) ($row['carbs'] ?? 0),
            'fat' => (float) ($row['fat'] ?? 0),
            'b6' => (float) ($row['b6'] ?? $microArr['vitamin_b6'] ?? 0),
            'b9_folate' => (float) ($row['b9_folate'] ?? $microArr['vitamin_b9'] ?? 0),
            'b12' => (float) ($row['b12'] ?? $microArr['vitamin_b12'] ?? 0),
            'iron' => (float) ($row['iron'] ?? $microArr['iron'] ?? 0),
            'magnesium' => (float) ($row['magnesium'] ?? $microArr['magnesium'] ?? 0),
            'functional_tip' => isset($row['functional_tip']) ? (string) $row['functional_tip'] : null,
            'sickle_cell_support_message' => isset($row['sickle_cell_support_message']) ? (string) $row['sickle_cell_support_message'] : null,
            'is_verified' => true,
            'fdc_key_nutrients' => is_array($row['fdc_key_nutrients'] ?? null) ? $row['fdc_key_nutrients'] : null,
            'micronutrients' => is_array($micro) ? $micro : [],
        ];
    }

    /**
     * @return array{quantity_g: float, quantity_source: 'parsed_input'|'library_portion'|'default_100g'}
     */
    private function estimateGramsFromPhrase(string $phrase, Ingredient $ingredient): array
    {
        $parsed = $this->parseExplicitMassGrams($phrase);

        if ($parsed !== null) {
            return [
                'quantity_g' => $parsed,
                'quantity_source' => 'parsed_input',
            ];
        }

        $portion = (float) ($ingredient->portion_grams ?? 0);

        if ($portion > 0) {
            return [
                'quantity_g' => $portion,
                'quantity_source' => 'library_portion',
            ];
        }

        return [
            'quantity_g' => 100.0,
            'quantity_source' => 'default_100g',
        ];
    }

    private function parseExplicitMassGrams(string $phrase): ?float
    {
        if (preg_match('/(\d+(?:\.\d+)?)\s*g(?:ram|grams)?\b/iu', $phrase, $m) === 1) {
            return max(0.0, (float) $m[1]);
        }

        if (preg_match('/(\d+(?:\.\d+)?)\s*kg\b/iu', $phrase, $m) === 1) {
            return max(0.0, (float) $m[1] * 1000.0);
        }

        if (preg_match('/(\d+(?:\.\d+)?)\s*oz\b/iu', $phrase, $m) === 1) {
            return max(0.0, (float) $m[1] * 28.3495);
        }

        if (preg_match('/(\d+(?:\.\d+)?)\s*(?:lb|lbs|pound|pounds)\b/iu', $phrase, $m) === 1) {
            return max(0.0, (float) $m[1] * 453.592);
        }

        return null;
    }
}
