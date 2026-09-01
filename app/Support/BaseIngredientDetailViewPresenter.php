<?php

namespace App\Support;

use App\Models\Ingredient;

/**
 * Read-only MealDetailView-shaped payload for prepared base ingredients.
 */
final class BaseIngredientDetailViewPresenter
{
    /**
     * @return array<string, mixed>
     */
    public function build(Ingredient $ingredient): array
    {
        $ingredient->loadMissing(['components']);

        $nutrition = $this->nutritionPer100GramsCalculatorShape($ingredient);

        $childIds = [];
        $ingredientLines = [];
        $ingredientItems = [];

        foreach (MealIngredientDisplayOrder::sortedIngredients($ingredient->components) as $child) {
            $childIds[] = (int) $child->id;
            $grams = (float) ($child->pivot->amount_grams ?? 0);
            $line = $grams > 0.0
                ? sprintf('%sg %s', $this->formatTrimmedDecimal($grams, 2), $child->name)
                : $child->name;

            $ingredientLines[] = $line;
            $ingredientItems[] = $this->structuredIngredientItem($child, $line);
        }

        if ($ingredientLines === []) {
            $ingredientLines = [__('No component ingredients on file.')];
        }

        $hasG6pdTrigger = IngredientG6pdSafety::ingredientHasEffectiveG6pdTrigger($ingredient)
            || ($childIds !== [] && IngredientG6pdSafety::mealContainsG6pdTrigger($childIds));

        $safetyAlertTags = $childIds !== []
            ? $this->safetyAlertTagsForIngredientIds($childIds)
            : [];
        $safetyAlerts = $this->safetyAlertsForDetailView($safetyAlertTags);

        if ($hasG6pdTrigger) {
            $safetyAlerts = array_values(array_filter(
                $safetyAlerts,
                static fn (array $alert): bool => ! str_contains(strtoupper($alert['label']), 'G6PD'),
            ));
        }

        $sickleCellHighlights = SickleCellNutrientRdi::highlightBadgeLabels($nutrition);
        $instructionsRaw = trim((string) ($ingredient->instructions ?? ''));

        $dietaryTags = [];
        if (is_array($ingredient->diet_tags)) {
            foreach ($ingredient->diet_tags as $tag) {
                if (is_string($tag) && trim($tag) !== '') {
                    $dietaryTags[] = trim($tag);
                }
            }
        }

        return [
            'shortDescription' => trim((string) ($ingredient->description ?? '')),
            'cyclePhases' => [],
            'dietaryTags' => array_values(array_unique($dietaryTags)),
            'hasG6pdTrigger' => $hasG6pdTrigger,
            'safetyAlerts' => $safetyAlerts,
            'sickleCellHighlights' => $sickleCellHighlights,
            'nutritionalData' => $this->nutritionalDataPer100gSidebar($nutrition),
            'ingredients' => $ingredientLines,
            'ingredientItems' => $ingredientItems,
            'instructions' => $this->instructionsLinesFromText($instructionsRaw),
            'imageUrl' => MealImagePath::resolveUrl($ingredient->image_path, $ingredient->name),
            'imageAlt' => $ingredient->name,
            'nutritionSubheading' => __('Per 100 g totals'),
            'sickleRdiFootnote' => __('High Source: ≥20%% of daily RDI per 100 g'),
        ];
    }

    /**
     * @return array{line: string, ingredientId: int, isBaseRecipe: bool}
     */
    public function structuredIngredientItem(Ingredient $ingredient, string $line): array
    {
        return [
            'line' => $line,
            'ingredientId' => (int) $ingredient->id,
            'isBaseRecipe' => $ingredient->isPreparedBaseIngredient(),
        ];
    }

    /**
     * @return array<string, float>
     */
    private function nutritionPer100GramsCalculatorShape(Ingredient $ingredient): array
    {
        $micros = is_array($ingredient->micronutrients) ? $ingredient->micronutrients : [];

        return [
            'calories' => (float) $ingredient->calories,
            'protein' => (float) $ingredient->protein,
            'carbs' => (float) $ingredient->carbs,
            'fat' => (float) $ingredient->fat,
            'fiber' => (float) ($micros['fiber'] ?? 0),
            'sugar' => (float) ($micros['sugar'] ?? 0),
            'vitamin_a' => (float) ($micros['vitamin_a'] ?? 0),
            'vitamin_c' => (float) ($micros['vitamin_c'] ?? 0),
            'vitamin_d' => (float) ($micros['vitamin_d'] ?? 0),
            'vitamin_e' => (float) ($micros['vitamin_e'] ?? 0),
            'vitamin_k2' => (float) ($micros['vitamin_k2'] ?? 0),
            'b9_folate' => (float) $ingredient->b9_folate,
            'b12' => (float) $ingredient->b12,
            'b6' => (float) $ingredient->b6,
            'calcium' => (float) ($micros['calcium'] ?? 0),
            'iron' => (float) $ingredient->iron,
            'magnesium' => (float) $ingredient->magnesium,
            'potassium' => (float) ($micros['potassium'] ?? 0),
            'zinc' => (float) ($micros['zinc'] ?? 0),
            'sodium' => (float) ($micros['sodium'] ?? 0),
        ];
    }

    /**
     * @param  array<string, float>  $nutrition
     * @return array<string, mixed>
     */
    private function nutritionalDataPer100gSidebar(array $nutrition): array
    {
        $calories = (float) ($nutrition['calories'] ?? 0);
        $protein = (float) ($nutrition['protein'] ?? 0);
        $carbs = (float) ($nutrition['carbs'] ?? 0);
        $fat = (float) ($nutrition['fat'] ?? 0);
        $fiber = (float) ($nutrition['fiber'] ?? 0);
        $sugar = (float) ($nutrition['sugar'] ?? 0);
        $netCarbs = max(0.0, $carbs - $fiber);

        $macroRows = [
            ['label' => __('Total calories'), 'value' => (string) (int) round($calories)],
            ['label' => __('Protein (g)'), 'value' => $this->formatTrimmedDecimal($protein, 1), 'valueClass' => 'text-[#916A00]'],
            ['label' => __('Fats (g)'), 'value' => $this->formatTrimmedDecimal($fat, 1), 'valueClass' => 'text-[#2F4C9B]'],
            ['label' => __('Net carbs (g)'), 'value' => $this->formatTrimmedDecimal($netCarbs, 1), 'valueClass' => 'text-[#8F55A8]'],
            ['label' => __('Fiber (g)'), 'value' => $this->formatTrimmedDecimal($fiber, 1)],
            ['label' => __('Sugar (g)'), 'value' => $this->formatTrimmedDecimal($sugar, 1)],
        ];

        $vitaminRows = [
            ['label' => __('Vitamin A (mcg RAE)'), 'value' => $this->formatTrimmedDecimal((float) ($nutrition['vitamin_a'] ?? 0), 1)],
            ['label' => __('Vitamin C (mg)'), 'value' => $this->formatTrimmedDecimal((float) ($nutrition['vitamin_c'] ?? 0), 1)],
            ['label' => __('Vitamin D (mcg)'), 'value' => $this->formatTrimmedDecimal((float) ($nutrition['vitamin_d'] ?? 0), 1)],
            ['label' => __('Vitamin E (mg)'), 'value' => $this->formatTrimmedDecimal((float) ($nutrition['vitamin_e'] ?? 0), 1)],
            ['label' => __('Vitamin K2 (mcg)'), 'value' => $this->formatTrimmedDecimal((float) ($nutrition['vitamin_k2'] ?? 0), 1)],
            ['label' => __('Folate B9 (mcg)'), 'value' => $this->formatTrimmedDecimal((float) ($nutrition['b9_folate'] ?? 0), 1)],
            ['label' => __('Vitamin B12 (mcg)'), 'value' => $this->formatTrimmedDecimal((float) ($nutrition['b12'] ?? 0), 1)],
            ['label' => __('Vitamin B6 (mg)'), 'value' => $this->formatTrimmedDecimal((float) ($nutrition['b6'] ?? 0), 1)],
        ];

        $mineralRows = [
            ['label' => __('Calcium (mg)'), 'value' => $this->formatTrimmedDecimal((float) ($nutrition['calcium'] ?? 0), 1)],
            ['label' => __('Iron (mg)'), 'value' => $this->formatTrimmedDecimal((float) ($nutrition['iron'] ?? 0), 1)],
            ['label' => __('Magnesium (mg)'), 'value' => $this->formatTrimmedDecimal((float) ($nutrition['magnesium'] ?? 0), 1)],
            ['label' => __('Potassium (mg)'), 'value' => $this->formatTrimmedDecimal((float) ($nutrition['potassium'] ?? 0), 1)],
            ['label' => __('Zinc (mg)'), 'value' => $this->formatTrimmedDecimal((float) ($nutrition['zinc'] ?? 0), 1)],
            ['label' => __('Sodium (mg)'), 'value' => $this->formatTrimmedDecimal((float) ($nutrition['sodium'] ?? 0), 1)],
        ];

        return [
            'valueColumnLabel' => __('Per 100 g'),
            'sections' => [
                ['title' => __('Macros'), 'rows' => $macroRows],
                ['title' => __('Vitamins'), 'rows' => $vitaminRows],
                ['title' => __('Minerals'), 'rows' => $mineralRows],
            ],
        ];
    }

    /**
     * @return list<string>
     */
    private function instructionsLinesFromText(string $instructionsRaw): array
    {
        if ($instructionsRaw === '') {
            return [__('No written instructions on file.')];
        }

        $parts = preg_split('/\r\n|\r|\n/', $instructionsRaw) ?: [];
        $steps = [];

        foreach ($parts as $part) {
            $line = trim((string) $part);
            if ($line === '') {
                continue;
            }

            $line = preg_replace('/^Step\s+\d{1,2}\s*:\s*/iu', '', $line) ?? $line;
            $line = preg_replace('/^\d+[\.\)]\s*/', '', $line) ?? $line;
            $steps[] = trim($line);
        }

        if ($steps === []) {
            return [$instructionsRaw];
        }

        return array_values($steps);
    }

    /**
     * @param  list<string>  $safetyAlertTags
     * @return list<array{label: string, variant: string}>
     */
    private function safetyAlertsForDetailView(array $safetyAlertTags): array
    {
        $out = [];

        foreach ($safetyAlertTags as $label) {
            $label = trim((string) $label);
            if ($label === '') {
                continue;
            }

            $variant = str_contains(strtoupper($label), 'G6PD') ? 'g6pd' : 'allergy';
            $out[] = ['label' => $label, 'variant' => $variant];
        }

        return $out;
    }

    /**
     * @param  list<int>  $ingredientIds
     * @return list<string>
     */
    private function safetyAlertTagsForIngredientIds(array $ingredientIds): array
    {
        if ($ingredientIds === []) {
            return [];
        }

        $labels = [];
        $rows = Ingredient::query()
            ->whereIn('id', $ingredientIds)
            ->get(['id', 'common_allergens', 'is_g6pd_trigger']);

        foreach ($rows as $row) {
            foreach (IngredientAllergenCatalog::labelsFromSlugs(
                is_array($row->common_allergens) ? $row->common_allergens : [],
            ) as $label) {
                $labels[$label] = true;
            }
        }

        return IngredientG6pdSafety::mergeTriggerIntoSafetyLabels(
            array_keys($labels),
            IngredientG6pdSafety::mealContainsG6pdTrigger($ingredientIds),
        );
    }

    private function formatTrimmedDecimal(float $value, int $decimals): string
    {
        if (! is_finite($value)) {
            return '0';
        }

        $formatted = number_format($value, $decimals, '.', '');

        return rtrim(rtrim($formatted, '0'), '.') ?: '0';
    }
}
