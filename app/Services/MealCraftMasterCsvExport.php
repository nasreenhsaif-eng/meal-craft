<?php

namespace App\Services;

use App\Enums\CyclePhase;
use App\Models\Meal;
use App\Support\MealCsvHeaderCatalog;
use App\Support\MealImagePath;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Meal Craft master CSV: editorial fields, targets vs engine totals, and variance notes.
 */
final class MealCraftMasterCsvExport
{
    /** @var list<string> */
    public const HEADERS = MealCsvHeaderCatalog::MASTER_HEADERS;

    /**
     * When {@see Meal::$image_path} is empty, emit this placeholder so spreadsheets stay explicit.
     */
    public const MISSING_PHOTO_PLACEHOLDER = 'NO_PHOTO_URL';

    /**
     * @param  array{
     *     calories?: float|null,
     *     protein?: float|null,
     *     fat?: float|null,
     *     net_carbs?: float|null
     * }  $targets
     * @param  array{
     *     calories: float,
     *     protein: float,
     *     fat: float,
     *     net_carbs: float
     * }  $calculated
     */
    public static function formatVarianceNotes(array $targets, array $calculated): string
    {
        $parts = [];
        $pairs = [
            'kcal' => ['t' => $targets['calories'] ?? null, 'c' => $calculated['calories']],
            'protein_g' => ['t' => $targets['protein'] ?? null, 'c' => $calculated['protein']],
            'fat_g' => ['t' => $targets['fat'] ?? null, 'c' => $calculated['fat']],
            'net_carbs_g' => ['t' => $targets['net_carbs'] ?? null, 'c' => $calculated['net_carbs']],
        ];

        foreach ($pairs as $key => $row) {
            $t = $row['t'];
            if ($t === null) {
                continue;
            }
            $delta = (float) $t - (float) $row['c'];
            $parts[] = sprintf('%s: %s', $key, self::formatFloat($delta));
        }

        return implode('; ', $parts);
    }

    /**
     * @param  resource  $handle
     */
    public function writeFullLibraryToStream($handle): void
    {
        fputcsv($handle, self::HEADERS, ',', '"', '\\');

        Meal::queryForMealLibrary()
            ->with(['ingredients' => function (BelongsToMany $query): void {
                $query->orderBy('ingredients.name');
            }])
            ->orderBy('name')
            ->each(function (Meal $meal) use ($handle): void {
                fputcsv($handle, $this->rowForMeal($meal), ',', '"', '\\');
            });
    }

    /**
     * @return list<string|float|int>
     */
    public function rowForMeal(Meal $meal): array
    {
        $nutrition = $meal->ingredients->isEmpty()
            ? null
            : RecipeNutritionCalculator::fromMeal($meal);

        $calcCal = $nutrition !== null
            ? (float) ($nutrition['calories'] ?? 0)
            : (float) ($meal->total_calories ?? 0);
        $calcProtein = $nutrition !== null
            ? (float) ($nutrition['protein'] ?? 0)
            : (float) ($meal->total_protein ?? 0);
        $calcFat = $nutrition !== null
            ? (float) ($nutrition['fat'] ?? 0)
            : (float) ($meal->total_fat ?? 0);
        $calcCarbs = $nutrition !== null
            ? (float) ($nutrition['carbs'] ?? 0)
            : (float) ($meal->total_carbs ?? 0);
        $calcFiber = $nutrition !== null
            ? (float) ($nutrition['fiber'] ?? 0)
            : (float) ($meal->total_fiber ?? 0);
        $calcNetCarbs = max(0.0, $calcCarbs - $calcFiber);

        $targetCal = $this->optionalTargetNumeric($meal, 'target_calories');
        $targetProtein = $this->optionalTargetNumeric($meal, 'target_protein');
        $targetFat = $this->optionalTargetNumeric($meal, 'target_fat');
        $targetNetCarbs = $this->optionalTargetNumeric($meal, 'target_net_carbs')
            ?? $this->optionalTargetNumeric($meal, 'target_carbs');

        $variance = self::formatVarianceNotes(
            [
                'calories' => $targetCal,
                'protein' => $targetProtein,
                'fat' => $targetFat,
                'net_carbs' => $targetNetCarbs,
            ],
            [
                'calories' => $calcCal,
                'protein' => $calcProtein,
                'fat' => $calcFat,
                'net_carbs' => $calcNetCarbs,
            ],
        );

        $shortDescriptionCell = $this->shortDescriptionCellForCsv($meal);
        $instructionsCell = $this->instructionsCellForCsv($meal);

        return [
            $meal->name,
            $shortDescriptionCell,
            $this->mealPlanTagsCell($meal),
            self::canonicalCyclePhaseLabels($meal),
            $this->dietaryTagsCell($meal),
            $this->safetyAlertsCell($meal),
            $this->ingredientsReadableCell($meal),
            $instructionsCell,
            $this->photoUrlForMeal($meal),
            $targetCal !== null ? self::formatFloat($targetCal) : '',
            $targetProtein !== null ? self::formatFloat($targetProtein) : '',
            $targetFat !== null ? self::formatFloat($targetFat) : '',
            $targetNetCarbs !== null ? self::formatFloat($targetNetCarbs) : '',
            self::formatFloat($calcCal),
            self::formatFloat($calcProtein),
            self::formatFloat($calcFat),
            self::formatFloat($calcNetCarbs),
            $variance,
        ];
    }

    public static function canonicalCyclePhaseLabels(Meal $meal): string
    {
        $labels = [];
        $raw = is_array($meal->cycle_phases) ? $meal->cycle_phases : [];
        foreach ($raw as $v) {
            if (! is_string($v) || $v === '') {
                continue;
            }
            $enum = CyclePhase::tryFrom($v);
            if ($enum === null) {
                continue;
            }
            $labels[] = match ($enum) {
                CyclePhase::Menstrual => 'Menstrual',
                CyclePhase::Follicular => 'Follicular',
                CyclePhase::Ovulatory => 'Ovulatory',
                CyclePhase::Luteal => 'Luteal',
            };
        }
        $labels = array_values(array_unique($labels));
        if ($labels === [] && $meal->cycle_phase instanceof CyclePhase) {
            $labels[] = match ($meal->cycle_phase) {
                CyclePhase::Menstrual => 'Menstrual',
                CyclePhase::Follicular => 'Follicular',
                CyclePhase::Ovulatory => 'Ovulatory',
                CyclePhase::Luteal => 'Luteal',
            };
        }

        return implode(' | ', $labels);
    }

    private function mealPlanTagsCell(Meal $meal): string
    {
        $labels = [];
        if (is_array($meal->meal_plan_tags)) {
            foreach ($meal->meal_plan_tags as $t) {
                if (is_string($t) && trim($t) !== '') {
                    $labels[] = trim($t);
                }
            }
        }
        if ($labels === []) {
            $single = is_string($meal->meal_plan_tag ?? null) ? trim((string) $meal->meal_plan_tag) : '';
            if ($single !== '') {
                $labels[] = $single;
            }
        }
        $labels = array_values(array_unique($labels));
        sort($labels);

        return implode(', ', $labels);
    }

    private function dietaryTagsCell(Meal $meal): string
    {
        $labels = [];
        $dietTags = is_array($meal->diet_tags) ? $meal->diet_tags : [];
        foreach ($dietTags as $tag) {
            $label = is_string($tag) ? trim($tag) : '';
            if ($label !== '') {
                $labels[$label] = true;
            }
        }

        $out = array_keys($labels);
        sort($out);

        return implode(', ', $out);
    }

    private function safetyAlertsCell(Meal $meal): string
    {
        $tags = is_array($meal->safety_alert_tags) ? $meal->safety_alert_tags : [];
        $labels = [];
        foreach ($tags as $tag) {
            $label = is_string($tag) ? trim($tag) : '';
            if ($label !== '') {
                $labels[$label] = true;
            }
        }
        $out = array_keys($labels);
        sort($out);

        return implode(', ', $out);
    }

    private function ingredientsReadableCell(Meal $meal): string
    {
        if ($meal->ingredients->isEmpty()) {
            return '';
        }

        $parts = [];
        foreach ($meal->ingredients as $ingredient) {
            $grams = $this->pivotGrams($ingredient->pivot);
            if ($grams <= 0) {
                continue;
            }
            $parts[] = trim($ingredient->name).' ('.$this->formatGrams($grams).'g)';
        }

        return implode(' | ', $parts);
    }

    private function photoUrlForMeal(Meal $meal): string
    {
        $path = $meal->image_path;
        if ($path === null || $path === '') {
            return self::MISSING_PHOTO_PLACEHOLDER;
        }

        $url = MealImagePath::resolveUrl($path);

        return $url === '' ? self::MISSING_PHOTO_PLACEHOLDER : $url;
    }

    private function shortDescriptionCellForCsv(Meal $meal): string
    {
        $preferred = trim((string) ($meal->short_description ?? ''));

        return $preferred !== '' ? $preferred : trim((string) ($meal->highlight ?? ''));
    }

    private function instructionsCellForCsv(Meal $meal): string
    {
        $preferred = trim((string) ($meal->instructions ?? ''));

        return $preferred !== '' ? $preferred : trim((string) ($meal->description ?? ''));
    }

    private function optionalTargetNumeric(Meal $meal, string $attribute): ?float
    {
        if (! array_key_exists($attribute, $meal->getAttributes())) {
            return null;
        }

        $raw = $meal->getAttribute($attribute);
        if ($raw === null || $raw === '') {
            return null;
        }

        return is_numeric($raw) ? (float) $raw : null;
    }

    /**
     * @param  Pivot|null  $pivot
     */
    private function pivotGrams(?object $pivot): float
    {
        if ($pivot === null) {
            return 0.0;
        }

        $gramsRaw = $pivot->amount_grams ?? null;
        if ($gramsRaw !== null && $gramsRaw !== '' && is_numeric($gramsRaw) && (float) $gramsRaw > 0) {
            return (float) $gramsRaw;
        }

        $amount = $pivot->amount ?? null;

        return ($amount !== null && $amount !== '' && is_numeric($amount)) ? (float) $amount : 0.0;
    }

    private function formatGrams(float $grams): string
    {
        $formatted = rtrim(rtrim(number_format($grams, 4, '.', ''), '0'), '.');

        return $formatted === '' ? '0' : $formatted;
    }

    private static function formatFloat(float $value): string
    {
        $formatted = rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');

        return $formatted === '' ? '0' : $formatted;
    }

    /**
     * Meal Library “Download CSV template” column names (admin UI).
     *
     * @var list<string>
     */
    public const MEAL_CRAFT_CSV_TEMPLATE_HEADERS = [
        'Meal Name',
        'Meal Type',
        'Ingredients String',
        'Target Calories',
        'Target Protein',
        'Target Carbs',
        'Target Fat',
        'Batch Calories',
        'Batch Protein',
        'Batch Carbs',
        'Batch Fat',
        'Is Bulk',
        'Servings Count',
        'Meal Plan Tag',
        'Cycle phase',
        'Safety Alerts',
        'Image_URL',
        'Short Description',
        'Instructions',
    ];

    /**
     * CSV document for the admin meal-library template download (header + one sample row).
     */
    public static function mealCraftCsvTemplateCsv(): string
    {
        $handle = fopen('php://memory', 'w+');
        if ($handle === false) {
            return '';
        }

        fputcsv($handle, self::MEAL_CRAFT_CSV_TEMPLATE_HEADERS, ',', '"', '\\');
        fputcsv($handle, [
            'Coconut Chicken Curry',
            'Meal',
            'Chicken thigh (500g) | Coconut milk (400ml) | Red curry paste (45g) | Fish sauce (15ml) | Palm sugar (8g) | Jasmine rice (200g) | Thai basil (10g)',
            '620',
            '48',
            '42',
            '22',
            '',
            '',
            '',
            '',
            'false',
            '4',
            'Balanced',
            'Follicular',
            '',
            '/images/meals/coconut-chicken-curry.jpg',
            'Rich red curry with coconut milk and tender chicken; serve over jasmine rice with fresh Thai basil.',
            'Brown chicken | Simmer curry with coconut milk | Finish with basil; serve hot over jasmine rice.',
        ], ',', '"', '\\');
        rewind($handle);
        $csv = stream_get_contents($handle) ?: '';
        fclose($handle);

        return $csv;
    }
}
