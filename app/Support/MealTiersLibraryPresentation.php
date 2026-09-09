<?php

namespace App\Support;

use App\Models\Ingredient;
use App\Models\Meal;
use App\Models\MealCalorieTier;
use App\Services\RecipeNutritionCalculator;

/**
 * Admin Meal Tiers Library row / nutrition payloads.
 */
final class MealTiersLibraryPresentation
{
    /**
     * @param  array<string, float>  $nutrition
     * @return array{valueColumnLabel: string, sections: list<array{title: string, rows: list<array{label: string, value: string, valueClass?: string}>}>}
     */
    public static function nutritionalData(array $nutrition): array
    {
        $calories = (float) ($nutrition['calories'] ?? 0);
        $protein = (float) ($nutrition['protein'] ?? 0);
        $carbs = (float) ($nutrition['carbs'] ?? 0);
        $fat = (float) ($nutrition['fat'] ?? 0);
        $fiber = (float) ($nutrition['fiber'] ?? 0);
        $sugar = (float) ($nutrition['sugar'] ?? 0);
        $netCarbs = max(0.0, $carbs - $fiber);

        return [
            'valueColumnLabel' => __('Per serving'),
            'sections' => [
                [
                    'title' => __('Macros'),
                    'rows' => [
                        ['label' => __('Total calories'), 'value' => (string) (int) round($calories)],
                        ['label' => __('Protein (g)'), 'value' => self::decimal($protein), 'valueClass' => 'text-[#916A00]'],
                        ['label' => __('Fats (g)'), 'value' => self::decimal($fat), 'valueClass' => 'text-[#2F4C9B]'],
                        ['label' => __('Carbs (g)'), 'value' => self::decimal($carbs), 'valueClass' => 'text-[#8F55A8]'],
                        ['label' => __('Net carbs (g)'), 'value' => self::decimal($netCarbs)],
                        ['label' => __('Fiber (g)'), 'value' => self::decimal($fiber)],
                        ['label' => __('Sugar (g)'), 'value' => self::decimal($sugar)],
                    ],
                ],
                [
                    'title' => __('Vitamins'),
                    'rows' => [
                        ['label' => __('Vitamin A (mcg RAE)'), 'value' => self::decimal((float) ($nutrition['vitamin_a'] ?? 0))],
                        ['label' => __('Vitamin C (mg)'), 'value' => self::decimal((float) ($nutrition['vitamin_c'] ?? 0))],
                        ['label' => __('Vitamin D (mcg)'), 'value' => self::decimal((float) ($nutrition['vitamin_d'] ?? 0))],
                        ['label' => __('Vitamin E (mg)'), 'value' => self::decimal((float) ($nutrition['vitamin_e'] ?? 0))],
                        ['label' => __('Vitamin K2 (mcg)'), 'value' => self::decimal((float) ($nutrition['vitamin_k2'] ?? 0))],
                        ['label' => __('Folate B9 (mcg)'), 'value' => self::decimal((float) ($nutrition['b9_folate'] ?? 0))],
                        ['label' => __('Vitamin B12 (mcg)'), 'value' => self::decimal((float) ($nutrition['b12'] ?? 0))],
                        ['label' => __('Vitamin B6 (mg)'), 'value' => self::decimal((float) ($nutrition['b6'] ?? 0))],
                    ],
                ],
                [
                    'title' => __('Minerals'),
                    'rows' => [
                        ['label' => __('Calcium (mg)'), 'value' => self::decimal((float) ($nutrition['calcium'] ?? 0))],
                        ['label' => __('Iron (mg)'), 'value' => self::decimal((float) ($nutrition['iron'] ?? 0))],
                        ['label' => __('Magnesium (mg)'), 'value' => self::decimal((float) ($nutrition['magnesium'] ?? 0))],
                        ['label' => __('Potassium (mg)'), 'value' => self::decimal((float) ($nutrition['potassium'] ?? 0))],
                        ['label' => __('Zinc (mg)'), 'value' => self::decimal((float) ($nutrition['zinc'] ?? 0))],
                        ['label' => __('Sodium (mg)'), 'value' => self::decimal((float) ($nutrition['sodium'] ?? 0))],
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function calorieTierPayload(Meal $meal, MealCalorieTier $tier): array
    {
        $nutrition = is_array($tier->nutrition) && $tier->nutrition !== []
            ? $tier->nutrition
            : [
                'calories' => $tier->total_calories,
                'protein' => $tier->total_protein,
                'carbs' => $tier->total_carbs,
                'fat' => $tier->total_fat,
            ];

        $ingredients = [];
        foreach (self::sortedTierIngredients($tier) as $ingredient) {
            $grams = (float) ($ingredient->pivot->amount_grams ?? 0);
            $ingredients[] = [
                'ingredient_id' => $ingredient->id,
                'name' => $ingredient->name,
                'amount_grams' => $grams,
                'line' => self::ingredientAmountLine($ingredient, $grams),
                'is_base_recipe' => $ingredient->isPreparedBaseIngredient(),
            ];
        }

        $buckets = MealTiersProteinFamily::bucketsForMeal($meal, (int) $tier->calorie_tier);
        $eggCount = MealTiersCalorieTabs::savoryEggMinimumForMeal($meal, (int) $tier->calorie_tier);
        $yieldSummary = IngredientCookingYield::mealYieldSummary($tier->ingredients);

        return [
            'calorie_tier' => (int) $tier->calorie_tier,
            'designed_calories' => $tier->designed_calories,
            'macros' => [
                'calories' => (int) round((float) ($nutrition['calories'] ?? 0)),
                'protein' => round((float) ($nutrition['protein'] ?? 0), 1),
                'carbs' => round((float) ($nutrition['carbs'] ?? 0), 1),
                'fat' => round((float) ($nutrition['fat'] ?? 0), 1),
            ],
            'nutritionalData' => self::nutritionalData($nutrition),
            'ingredients' => $ingredients,
            'ingredientSections' => self::ingredientSections($tier),
            'ingredientsPrepNote' => RawPrepIngredientPresentation::ingredientsPrepNote(),
            'cookingYieldNote' => $yieldSummary['note'] !== '' ? $yieldSummary['note'] : null,
            'proteinFamily' => MealTiersProteinFamily::forMeal($meal),
            'buckets' => $buckets,
            'proteinGramsTarget' => self::proteinGramsTargetForMeal($meal, (int) $tier->calorie_tier),
            'eggCount' => $eggCount,
            'authored' => $tier->ingredients->isNotEmpty(),
        ];
    }

    public static function proteinGramsTargetForMeal(Meal $meal, int $calorieTier): ?float
    {
        $family = MealTiersProteinFamily::forMeal($meal);

        if (MealTiersProteinFamily::usesKitchenPackout($family)) {
            return ChickenKitchenPlateTargets::cookedProteinGramsForTier($calorieTier);
        }

        return MealTiersProteinFamily::proteinGramsForTier($calorieTier);
    }

    /**
     * @return list<Ingredient>
     */
    public static function sortedTierIngredients(MealCalorieTier $tier): array
    {
        return self::sortedIngredients($tier->ingredients);
    }

    /**
     * Protein, then carbs and vegetables, then fats and sauces, then seasonings.
     *
     * @return list<array{title: string, items: list<array{line: string, ingredientId: int, isBaseRecipe: bool}>}>
     */
    public static function ingredientSections(MealCalorieTier $tier): array
    {
        return self::ingredientSectionsFromIngredients($tier->ingredients);
    }

    /**
     * @param  iterable<Ingredient>  $ingredients
     * @return list<array{title: string, items: list<array{line: string, ingredientId: int, isBaseRecipe: bool}>}>
     */
    public static function ingredientSectionsFromIngredients(iterable $ingredients): array
    {
        $grouped = [
            'protein' => ['title' => __('Protein'), 'items' => []],
            'carbs_veg' => ['title' => __('Carbs and vegetables'), 'items' => []],
            'fat_sauce' => ['title' => __('Fats and sauces'), 'items' => []],
            'seasoning' => ['title' => __('Seasonings'), 'items' => []],
            'other' => ['title' => __('Other'), 'items' => []],
        ];

        foreach (self::sortedIngredients($ingredients) as $ingredient) {
            $grams = (float) ($ingredient->pivot->amount_grams ?? 0);
            $line = self::ingredientAmountLine($ingredient, $grams);
            $key = self::tiersLibraryGroupKey($ingredient);
            $grouped[$key]['items'][] = self::structuredIngredientItem($ingredient, $line);
        }

        return array_values(array_filter(
            $grouped,
            static fn (array $section): bool => $section['items'] !== [],
        ));
    }

    /**
     * @return array{line: string, ingredientId: int, isBaseRecipe: bool}
     */
    public static function structuredIngredientItem(Ingredient $ingredient, string $line): array
    {
        return [
            'line' => $line,
            'ingredientId' => (int) $ingredient->id,
            'isBaseRecipe' => $ingredient->isPreparedBaseIngredient(),
        ];
    }

    public static function tiersLibraryGroupRank(Ingredient $ingredient): int
    {
        return match (self::tiersLibraryGroupKey($ingredient)) {
            'protein' => 10,
            'carbs_veg' => 20,
            'fat_sauce' => 30,
            'seasoning' => 40,
            default => 50,
        };
    }

    /**
     * @return 'protein'|'carbs_veg'|'fat_sauce'|'seasoning'|'other'
     */
    public static function tiersLibraryGroupKey(Ingredient $ingredient): string
    {
        $rank = MealIngredientDisplayOrder::groupRank($ingredient);

        if ($rank === MealIngredientDisplayOrder::GROUP_PROTEIN) {
            return 'protein';
        }

        $name = strtolower($ingredient->name);

        if (str_contains($name, 'garlic') && ! str_contains($name, 'sauce')) {
            return 'seasoning';
        }

        return match ($rank) {
            MealIngredientDisplayOrder::GROUP_CARBS, MealIngredientDisplayOrder::GROUP_VEGETABLES => 'carbs_veg',
            MealIngredientDisplayOrder::GROUP_FATS, MealIngredientDisplayOrder::GROUP_SAUCES => 'fat_sauce',
            MealIngredientDisplayOrder::GROUP_HERBS_SPICES => 'seasoning',
            default => 'other',
        };
    }

    /**
     * @param  iterable<Ingredient>  $ingredients
     * @return list<Ingredient>
     */
    public static function sortedIngredients(iterable $ingredients): array
    {
        $items = is_array($ingredients) ? $ingredients : iterator_to_array($ingredients);

        usort($items, static function (Ingredient $left, Ingredient $right): int {
            $rank = self::tiersLibraryGroupRank($left) <=> self::tiersLibraryGroupRank($right);

            if ($rank !== 0) {
                return $rank;
            }

            $sub = self::tiersLibrarySubRank($left) <=> self::tiersLibrarySubRank($right);

            if ($sub !== 0) {
                return $sub;
            }

            return strcasecmp($left->name, $right->name);
        });

        return array_values($items);
    }

    /**
     * Within a combined group: carbs before vegetables; fats before sauces.
     */
    public static function tiersLibrarySubRank(Ingredient $ingredient): int
    {
        return match (MealIngredientDisplayOrder::groupRank($ingredient)) {
            MealIngredientDisplayOrder::GROUP_CARBS, MealIngredientDisplayOrder::GROUP_FATS => 0,
            MealIngredientDisplayOrder::GROUP_VEGETABLES, MealIngredientDisplayOrder::GROUP_SAUCES => 1,
            default => 0,
        };
    }

    /**
     * @param  array<int, float>  $gramsByIngredientId
     * @return array<string, float>
     */
    public static function nutritionFromGrams(array $gramsByIngredientId): array
    {
        $rows = [];
        foreach ($gramsByIngredientId as $ingredientId => $grams) {
            $rows[] = [
                'ingredient_id' => (int) $ingredientId,
                'amount_grams' => max(0.0, (float) $grams),
            ];
        }

        if ($rows === []) {
            return [];
        }

        return RecipeNutritionCalculator::fromRows($rows, applyMealCookingYield: true);
    }

    public static function ingredientAmountLine(Ingredient $ingredient, float $grams): string
    {
        if ($grams <= 0) {
            return $ingredient->name;
        }

        $formattedGrams = self::decimal($grams);

        if (EggIngredientPresentation::isEggIngredient($ingredient)) {
            return EggIngredientPresentation::formatLine($grams, $formattedGrams);
        }

        if (RawPrepIngredientPresentation::isRawPrepIngredient($ingredient)) {
            return RawPrepIngredientPresentation::formatLine($grams, $formattedGrams, $ingredient);
        }

        if (RawPrepIngredientPresentation::isCannedPrepIngredient($ingredient)) {
            return RawPrepIngredientPresentation::formatCannedLine($grams, $formattedGrams, $ingredient);
        }

        if (RawPrepIngredientPresentation::isDryWeightIngredient($ingredient)) {
            return RawPrepIngredientPresentation::formatDryLine($grams, $formattedGrams, $ingredient);
        }

        if (RawPrepIngredientPresentation::isPreCookedBaseIngredient($ingredient)) {
            return RawPrepIngredientPresentation::formatBaseLine($grams, $formattedGrams, $ingredient);
        }

        if (KitchenSpoonPresentation::appliesTo($ingredient)) {
            return KitchenSpoonPresentation::formatTierLine($ingredient, $grams, $formattedGrams);
        }

        return $ingredient->name.' — '.$formattedGrams.' g';
    }

    private static function decimal(float $value): string
    {
        if (! is_finite($value)) {
            return '0';
        }

        $formatted = number_format($value, 1, '.', '');

        return rtrim(rtrim($formatted, '0'), '.') ?: '0';
    }
}
