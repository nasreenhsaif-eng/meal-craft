<?php

namespace App\Support;

use App\Models\Ingredient;
use App\Models\Meal;
use App\Services\BalancedCanonicalMealRecipeRefiner;

/**
 * Explicit calorie-tab plates for the Meal Tiers Library.
 */
final class MealTiersAuthoredPlates
{
    public const ReferenceCalorieTier = 500;

    /**
     * Kitchen prep oil: ½ tbsp on every chicken plate tier.
     */
    public const HalfTablespoonOilGrams = 5.0;

    public const TablespoonOilGrams = 15.0;

    /**
     * Reference 500 kcal kitchen plate at 1000 ml (½ tbsp oil). Other tabs scale via {@see ChickenKitchenContainerScaler}.
     *
     * @var array<int, array<string, float>>
     */
    private const RosemaryGarlicChickenPlates = [
        500 => [
            RosemaryGarlicChickenBaseRecipe::NAME => 130.0,
            'Sweet Potato' => 180.0,
            'Spinach (Fresh)' => 25.0,
            'Mushrooms' => 35.0,
            'Olive Oil (Extra Virgin)' => self::HalfTablespoonOilGrams,
            'Black Pepper' => 1.0,
        ],
    ];

    /**
     * Reference 500 kcal pesto koosa plate: spiralized zucchini noodles + roasted pumpkin cubes.
     *
     * @var array<int, array<string, float>>
     */
    private const PestoChickenKoosaPlates = [
        500 => [
            'Chicken Breast' => 150.0,
            'Zucchini' => 200.0,
            'Pumpkin' => 150.0,
            'Cherry Tomatoes' => 45.0,
            'Basil Pesto (House)' => 25.0,
            'Olive Oil' => self::HalfTablespoonOilGrams,
            'Black Pepper' => 1.0,
        ],
    ];

    public const PestoChickenKoosaMealName = 'Pesto Chicken Koosa Noodles';

    /**
     * 500 kcal kitchen plate (½ tbsp oil for vegetables).
     *
     * @return array<string, float>|null ingredient name => grams
     */
    public static function gramsAt500ForMealName(string $name): ?array
    {
        return self::gramsAtTierForMealName($name, self::ReferenceCalorieTier);
    }

    /**
     * Authored plate for a calorie tab (exact tab calories, kitchen 5 g steps).
     *
     * @return array<string, float>|null ingredient name => grams
     */
    public static function gramsAtTierForMealName(string $name, int $calorieTier): ?array
    {
        $name = trim($name);

        if ($calorieTier !== self::ReferenceCalorieTier) {
            return null;
        }

        if ($name === BalancedCanonicalMealRecipeRefiner::ROSEMARY_GARLIC_CHICKEN_PLATE_NAME
            || $name === BalancedCanonicalMealRecipeRefiner::ROSEMARY_GARLIC_CHICKEN_PLATE_LEGACY_NAME) {
            return self::RosemaryGarlicChickenPlates[$calorieTier] ?? null;
        }

        if ($name === self::PestoChickenKoosaMealName) {
            return self::PestoChickenKoosaPlates[$calorieTier] ?? null;
        }

        return null;
    }

    /**
     * Kitchen olive-oil pour for chicken plate tabs (½ tbsp every tier).
     */
    public static function cookingOilGramsCapForTier(int $calorieTier): ?float
    {
        if (in_array($calorieTier, [400, 500, 550, 600, 700, 800], true)) {
            return self::HalfTablespoonOilGrams;
        }

        return null;
    }

    /**
     * @return array<int, float>|null ingredient id => grams
     */
    public static function gramsByIngredientIdForTier(Meal $meal, int $calorieTier): ?array
    {
        $byName = self::gramsAtTierForMealName((string) $meal->name, $calorieTier);

        if ($byName === null) {
            return null;
        }

        $byId = [];

        foreach ($byName as $ingredientName => $grams) {
            $ingredient = self::findIngredient($ingredientName);

            if ($ingredient === null) {
                continue;
            }

            $byId[$ingredient->id] = $grams;
        }

        return $byId === [] ? null : $byId;
    }

    /**
     * @return array<int, array{amount_grams: float, amount: float, unit: string}>|null
     */
    public static function baselineAttach(Meal $meal): ?array
    {
        $gramsByName = self::gramsAt500ForMealName((string) $meal->name);

        if ($gramsByName === null) {
            return null;
        }

        $attach = [];

        foreach ($gramsByName as $name => $grams) {
            $ingredient = self::findIngredient($name);

            if ($ingredient === null) {
                continue;
            }

            $attach[$ingredient->id] = [
                'amount_grams' => $grams,
                'amount' => $grams,
                'unit' => 'g',
            ];
        }

        return $attach === [] ? null : $attach;
    }

    public static function scalesFrom500(Meal $meal): bool
    {
        return self::gramsAt500ForMealName((string) $meal->name) !== null;
    }

    /**
     * @param  array<int, float>  $baselineGrams
     * @return array<int, float>
     */
    public static function scaleBaselineFrom500(array $baselineGrams, int $calorieTier): array
    {
        $scale = $calorieTier / self::ReferenceCalorieTier;
        $scaled = [];

        foreach ($baselineGrams as $ingredientId => $grams) {
            $scaled[(int) $ingredientId] = round((float) $grams * $scale, 2);
        }

        return $scaled;
    }

    private static function findIngredient(string $name): ?Ingredient
    {
        return Ingredient::query()
            ->where('name', $name)
            ->orderByDesc('is_verified')
            ->orderBy('id')
            ->first();
    }
}
