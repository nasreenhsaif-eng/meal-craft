<?php

namespace App\Support;

use App\Models\Ingredient;

/**
 * Side salads pack into a 500 ml paper container with dressing in a separate 20 ml cup.
 */
final class SideSaladPackaging
{
    public const CONTAINER_ML = 500;

    public const DRESSING_CUP_ML = 20;

    /** Fluffy leaves (rocca, purslane, arugula, romaine, kale). */
    public static function maxFluffyLeafGrams(): float
    {
        return (float) config('customer_nutrition.side_salad_packaging.max_fluffy_leaf_grams', 60.0);
    }

    /** Denser shredded cabbage / bok choy bases. */
    public static function maxDenseLeafGrams(): float
    {
        return (float) config('customer_nutrition.side_salad_packaging.max_dense_leaf_grams', 70.0);
    }

    /** Combined leafy greens in one salad. */
    public static function maxCombinedLeafGrams(): float
    {
        return (float) config('customer_nutrition.side_salad_packaging.max_combined_leaf_grams', 70.0);
    }

    public static function maxDressingGrams(): float
    {
        return (float) config('customer_nutrition.side_salad_packaging.max_dressing_grams', 20.0);
    }

    public static function isDressingIngredient(Ingredient|string $ingredient): bool
    {
        $name = strtolower(trim($ingredient instanceof Ingredient ? $ingredient->name : $ingredient));

        return str_contains($name, 'dressing') || str_contains($name, 'vinaigrette');
    }

    public static function isLeafyIngredient(Ingredient|string $ingredient): bool
    {
        $name = strtolower(trim($ingredient instanceof Ingredient ? $ingredient->name : $ingredient));

        foreach ([
            'rocca',
            'arugula',
            'purslane',
            'romaine',
            'lettuce',
            'kale',
            'spinach',
            'bok choy',
            'cabbage',
        ] as $needle) {
            if (str_contains($name, $needle)) {
                return true;
            }
        }

        return false;
    }

    public static function isDenseLeafyIngredient(Ingredient|string $ingredient): bool
    {
        $name = strtolower(trim($ingredient instanceof Ingredient ? $ingredient->name : $ingredient));

        return str_contains($name, 'cabbage') || str_contains($name, 'bok choy');
    }

    /**
     * @param  array<string, float>  $ingredientGrams
     * @return list<string>
     */
    public static function violationMessages(array $ingredientGrams): array
    {
        $messages = [];
        $leafTotal = 0.0;
        $dressingTotal = 0.0;

        foreach ($ingredientGrams as $name => $grams) {
            $grams = (float) $grams;

            if ($grams <= 0) {
                continue;
            }

            if (self::isDressingIngredient($name)) {
                $dressingTotal += $grams;

                if ($grams > self::maxDressingGrams() + 0.05) {
                    $messages[] = sprintf(
                        '%s is %.0fg — dressing cup holds %.0fml.',
                        $name,
                        $grams,
                        self::maxDressingGrams(),
                    );
                }
            }

            if (self::isLeafyIngredient($name)) {
                $leafTotal += $grams;
                $cap = self::isDenseLeafyIngredient($name)
                    ? self::maxDenseLeafGrams()
                    : self::maxFluffyLeafGrams();

                if ($grams > $cap + 0.05) {
                    $messages[] = sprintf(
                        '%s is %.0fg — too much for a %.0fml salad container (max %.0fg).',
                        $name,
                        $grams,
                        self::CONTAINER_ML,
                        $cap,
                    );
                }
            }
        }

        if ($leafTotal > self::maxCombinedLeafGrams() + 0.05) {
            $messages[] = sprintf(
                'Combined leafy greens are %.0fg — max %.0fg for a %.0fml container.',
                $leafTotal,
                self::maxCombinedLeafGrams(),
                self::CONTAINER_ML,
            );
        }

        if ($dressingTotal > self::maxDressingGrams() + 0.05 && count(array_filter(
            $ingredientGrams,
            fn ($g, $name) => self::isDressingIngredient((string) $name) && (float) $g > 0,
            ARRAY_FILTER_USE_BOTH,
        )) > 1) {
            $messages[] = sprintf(
                'Combined dressing pours are %.0fg — cup holds %.0fml.',
                $dressingTotal,
                self::maxDressingGrams(),
            );
        }

        return $messages;
    }
}
