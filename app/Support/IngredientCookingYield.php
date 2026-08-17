<?php

namespace App\Support;

use App\Models\Ingredient;

/**
 * Raw/dry meal amounts vs cooked nutrition density and plated yield.
 *
 * Library convention:
 * - Standard meal amounts are RAW or DRY prep weight (grams).
 * - Prepared base ingredients ({@see Ingredient::isPreparedBaseIngredient()}) are already
 *   finished/cooked product; meal amounts are grams of that finished product and use the
 *   base's stored per-100 g finished macros with no further yield conversion.
 * - When a non-base ingredient stores COOKED edible-state macros but is weighed dry/raw in
 *   meals, nutrition mass is scaled by {@see self::dryToCookedYield()} so dry grams are not
 *   multiplied by cooked-per-100 g values directly.
 * - Display yield estimates cooked plated weight for the yield/result note.
 */
final class IngredientCookingYield
{
    public const STATE_RAW_OR_DRY = 'raw_or_dry';

    public const STATE_COOKED = 'cooked';

    public const STATE_FINISHED_BASE = 'finished_base';

    /**
     * Explicit profiles for staples where meal amounts are dry/raw but macros may be cooked.
     *
     * @var array<string, array{macros_state: string, dry_to_cooked_yield: float}>
     */
    private const PROFILES = [
        // Meats — USDA raw macros; amounts are raw prep; yield shrinks for plated weight.
        ChickenBreastYield::RAW_INGREDIENT_NAME => [
            'macros_state' => self::STATE_RAW_OR_DRY,
            'dry_to_cooked_yield' => ChickenBreastYield::RAW_TO_COOKED_WEIGHT_RATIO,
        ],
        'Salmon' => [
            'macros_state' => self::STATE_RAW_OR_DRY,
            'dry_to_cooked_yield' => 0.78,
        ],
        'Salmon (Raw)' => [
            'macros_state' => self::STATE_RAW_OR_DRY,
            'dry_to_cooked_yield' => 0.78,
        ],
        'Beef Ground Lean' => [
            'macros_state' => self::STATE_RAW_OR_DRY,
            'dry_to_cooked_yield' => 0.75,
        ],
        'Beef Ribeye' => [
            'macros_state' => self::STATE_RAW_OR_DRY,
            'dry_to_cooked_yield' => 0.72,
        ],
        'Beef Chuck Roast' => [
            'macros_state' => self::STATE_RAW_OR_DRY,
            'dry_to_cooked_yield' => 0.70,
        ],
        'Beef Liver' => [
            'macros_state' => self::STATE_RAW_OR_DRY,
            'dry_to_cooked_yield' => 0.72,
        ],

        // Dry grains (macros are dry-state) — expand when cooked for plated yield only.
        'Basmati White' => [
            'macros_state' => self::STATE_RAW_OR_DRY,
            'dry_to_cooked_yield' => 2.6,
        ],
        'Basmati Brown' => [
            'macros_state' => self::STATE_RAW_OR_DRY,
            'dry_to_cooked_yield' => 2.5,
        ],
        'Brown Rice' => [
            'macros_state' => self::STATE_RAW_OR_DRY,
            'dry_to_cooked_yield' => 2.5,
        ],

        // Cooked-labeled rice rows (macros already cooked) — meal amounts treated as cooked.
        'Basmati Rice (White)' => [
            'macros_state' => self::STATE_COOKED,
            'dry_to_cooked_yield' => 1.0,
        ],
        'Basmati Rice (Brown)' => [
            'macros_state' => self::STATE_COOKED,
            'dry_to_cooked_yield' => 1.0,
        ],
        'Wild Rice (Cooked)' => [
            'macros_state' => self::STATE_COOKED,
            'dry_to_cooked_yield' => 1.0,
        ],

        // Legumes weighed dry in recipes but library macros often cooked USDA.
        'French Lentils' => [
            'macros_state' => self::STATE_COOKED,
            'dry_to_cooked_yield' => 2.5,
        ],
        'Black Lentils' => [
            'macros_state' => self::STATE_COOKED,
            'dry_to_cooked_yield' => 2.5,
        ],
        'Lentils (Red)' => [
            'macros_state' => self::STATE_RAW_OR_DRY,
            'dry_to_cooked_yield' => 2.5,
        ],
        'Black Beans' => [
            'macros_state' => self::STATE_COOKED,
            'dry_to_cooked_yield' => 2.4,
        ],
        'Chickpeas' => [
            'macros_state' => self::STATE_COOKED,
            'dry_to_cooked_yield' => 2.3,
        ],
        'Quinoa (White)' => [
            'macros_state' => self::STATE_COOKED,
            'dry_to_cooked_yield' => 1.0,
        ],
    ];

    public static function isFinishedBaseComponent(Ingredient $ingredient): bool
    {
        return $ingredient->isPreparedBaseIngredient();
    }

    /**
     * @return array{macros_state: string, dry_to_cooked_yield: float}
     */
    public static function profileFor(Ingredient $ingredient): array
    {
        if (self::isFinishedBaseComponent($ingredient)) {
            return [
                'macros_state' => self::STATE_FINISHED_BASE,
                'dry_to_cooked_yield' => 1.0,
            ];
        }

        $name = trim($ingredient->name);

        if (isset(self::PROFILES[$name])) {
            return self::PROFILES[$name];
        }

        return self::inferredProfile($ingredient);
    }

    /**
     * Grams to multiply against per-100 g macros for meal nutrition.
     *
     * Base recipes: finished portion grams unchanged.
     * Explicit cooked-macro staples weighed dry in meals: scale by dry→cooked yield.
     * Raw/dry-macro staples: use input grams (energy conserved through cooking).
     */
    public static function nutritionMassGrams(Ingredient $ingredient, float $inputGrams): float
    {
        $inputGrams = max(0.0, $inputGrams);

        if ($inputGrams <= 0) {
            return 0.0;
        }

        $profile = self::profileFor($ingredient);

        if ($profile['macros_state'] === self::STATE_FINISHED_BASE) {
            return round($inputGrams, 4);
        }

        // Only explicit catalog rows with cooked macros + expand yield rescale nutrition mass.
        $name = trim($ingredient->name);
        if (
            isset(self::PROFILES[$name])
            && $profile['macros_state'] === self::STATE_COOKED
            && $profile['dry_to_cooked_yield'] > 1.0
        ) {
            return round($inputGrams * $profile['dry_to_cooked_yield'], 4);
        }

        return round($inputGrams, 4);
    }

    /**
     * Estimated cooked / plated grams from a raw or dry meal amount (or finished base grams).
     */
    public static function estimatedCookedGrams(Ingredient $ingredient, float $inputGrams): float
    {
        $inputGrams = max(0.0, $inputGrams);

        if ($inputGrams <= 0) {
            return 0.0;
        }

        $profile = self::profileFor($ingredient);

        if ($profile['macros_state'] === self::STATE_FINISHED_BASE) {
            return round($inputGrams, 4);
        }

        $yield = (float) $profile['dry_to_cooked_yield'];

        if ($yield <= 0) {
            return round($inputGrams, 4);
        }

        // Cooked-macro staples already plated as dry→cooked for nutrition when yield > 1;
        // estimated cooked weight uses the same factor. Meats shrink (yield < 1).
        if ($profile['macros_state'] === self::STATE_COOKED && $yield > 1.0) {
            return round($inputGrams * $yield, 4);
        }

        if ($profile['macros_state'] === self::STATE_RAW_OR_DRY) {
            return round($inputGrams * $yield, 4);
        }

        return round($inputGrams, 4);
    }

    public static function dryToCookedYield(Ingredient $ingredient): float
    {
        return (float) self::profileFor($ingredient)['dry_to_cooked_yield'];
    }

    /**
     * Human label for ingredient list lines.
     */
    public static function amountStateLabel(Ingredient $ingredient): ?string
    {
        if (self::isFinishedBaseComponent($ingredient)) {
            return __('pre-cooked base');
        }

        $profile = self::profileFor($ingredient);

        if ($profile['macros_state'] === self::STATE_RAW_OR_DRY && $profile['dry_to_cooked_yield'] < 1.0) {
            return __('raw, before cooking');
        }

        if (
            ($profile['macros_state'] === self::STATE_RAW_OR_DRY && $profile['dry_to_cooked_yield'] > 1.0)
            || ($profile['macros_state'] === self::STATE_COOKED && $profile['dry_to_cooked_yield'] > 1.0)
        ) {
            return __('dry weight');
        }

        return null;
    }

    /**
     * @param  iterable<int, Ingredient>  $ingredients  Must include pivot amount_grams unless overridden.
     * @param  array<int, float>|null  $gramsByIngredientId  Optional adapted/display grams keyed by ingredient id.
     * @return array{
     *     estimated_cooked_grams: float,
     *     raw_or_dry_input_grams: float,
     *     finished_base_grams: float,
     *     note: string
     * }
     */
    public static function mealYieldSummary(iterable $ingredients, ?array $gramsByIngredientId = null): array
    {
        $cooked = 0.0;
        $rawInput = 0.0;
        $baseGrams = 0.0;

        foreach ($ingredients as $ingredient) {
            if (! $ingredient instanceof Ingredient) {
                continue;
            }

            $ingredientId = (int) $ingredient->id;
            $input = $gramsByIngredientId !== null && array_key_exists($ingredientId, $gramsByIngredientId)
                ? (float) $gramsByIngredientId[$ingredientId]
                : (float) ($ingredient->pivot->amount_grams ?? 0);

            if ($input <= 0) {
                continue;
            }

            if (self::isFinishedBaseComponent($ingredient)) {
                $baseGrams += $input;
                $cooked += $input;

                continue;
            }

            $rawInput += $input;
            $cooked += self::estimatedCookedGrams($ingredient, $input);
        }

        $cooked = round($cooked, 1);
        $rawInput = round($rawInput, 1);
        $baseGrams = round($baseGrams, 1);

        if ($cooked <= 0) {
            return [
                'estimated_cooked_grams' => 0.0,
                'raw_or_dry_input_grams' => $rawInput,
                'finished_base_grams' => $baseGrams,
                'note' => '',
            ];
        }

        $note = __('Estimated cooked yield: :grams g', ['grams' => self::formatGrams($cooked)]);

        if ($rawInput > 0 && abs($cooked - $rawInput) >= 1.0) {
            $note .= ' '.__('(from :raw g raw/dry ingredients', ['raw' => self::formatGrams($rawInput)]);
            if ($baseGrams > 0) {
                $note .= ' + '.__(':base g pre-cooked bases', ['base' => self::formatGrams($baseGrams)]);
            }
            $note .= ')';
        } elseif ($baseGrams > 0 && $rawInput <= 0) {
            $note = __('Pre-cooked base components: :grams g', ['grams' => self::formatGrams($baseGrams)]);
        }

        return [
            'estimated_cooked_grams' => $cooked,
            'raw_or_dry_input_grams' => $rawInput,
            'finished_base_grams' => $baseGrams,
            'note' => $note,
        ];
    }

    private static function formatGrams(float $grams): string
    {
        $formatted = rtrim(rtrim(number_format($grams, 1, '.', ''), '0'), '.');

        return $formatted === '' ? '0' : $formatted;
    }

    /**
     * @return array{macros_state: string, dry_to_cooked_yield: float}
     */
    private static function inferredProfile(Ingredient $ingredient): array
    {
        $name = strtolower(trim($ingredient->name));
        $calories = (float) ($ingredient->calories ?? 0);
        $category = strtolower((string) ($ingredient->usda_food_category ?? ''));

        if (str_contains($name, 'cooked') || str_contains($name, '(base)')) {
            // Named cooked products or leftover base naming without prepared category.
            if (str_contains($name, 'cooked')) {
                return [
                    'macros_state' => self::STATE_COOKED,
                    'dry_to_cooked_yield' => 1.0,
                ];
            }
        }

        $isGrainOrLegume = str_contains($category, 'grain')
            || str_contains($category, 'legume')
            || str_contains($category, 'bean')
            || str_contains($name, 'lentil')
            || str_contains($name, 'chickpea')
            || str_contains($name, 'quinoa')
            || preg_match('/\b(rice|bean|beans)\b/', $name) === 1;

        // Avoid treating arbitrary "* Rice" test stubs as dry staples unless calories look dry.
        if ($isGrainOrLegume) {
            // Dry USDA densities are typically >280 kcal/100 g; cooked are much lower.
            if ($calories >= 280) {
                return [
                    'macros_state' => self::STATE_RAW_OR_DRY,
                    'dry_to_cooked_yield' => 2.5,
                ];
            }

            if ($calories > 0 && $calories < 200) {
                return [
                    'macros_state' => self::STATE_COOKED,
                    // Without an explicit profile, treat listed grams as already edible/cooked.
                    'dry_to_cooked_yield' => 1.0,
                ];
            }
        }

        $isMeat = str_contains($category, 'protein')
            || str_contains($category, 'poultry')
            || str_contains($category, 'beef')
            || str_contains($category, 'fish')
            || str_contains($name, 'chicken')
            || str_contains($name, 'beef')
            || str_contains($name, 'salmon')
            || str_contains($name, 'liver');

        if ($isMeat) {
            return [
                'macros_state' => self::STATE_RAW_OR_DRY,
                'dry_to_cooked_yield' => 0.75,
            ];
        }

        return [
            'macros_state' => self::STATE_RAW_OR_DRY,
            'dry_to_cooked_yield' => 1.0,
        ];
    }
}
